<?php

namespace App\Services\Requests;

use App\Enums\ContactSource;
use App\Enums\DistressSignalStatus;
use App\Enums\WalletEntryType;
use App\Models\Business;
use App\Models\BusinessContact;
use App\Models\DistressSignal;
use App\Models\User;
use App\Notifications\DistressSignalAccepted;
use App\Notifications\DistressSignalReceived;
use App\Notifications\DistressSignalUnanswered;
use App\Services\Offers\RequestPricing;
use App\Services\Search\BusinessSearch;
use App\Services\Settings\PlatformSettings;
use App\Services\Wallets\InsufficientBalanceException;
use App\Services\Wallets\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Signal de détresse (C5) : envoyé à tous les garages dont la zone
 * d'intervention couvre le client ; le premier qui accepte est débité.
 */
class DistressSignalService
{
    public function __construct(
        private readonly BusinessSearch $search,
        private readonly PlatformSettings $settings,
        private readonly RequestPricing $pricing,
        private readonly WalletService $wallets,
    ) {}

    /**
     * @param  array{message: string, latitude: float, longitude: float, service_id?: ?int, vehicle_type_id?: ?int}  $data
     *
     * @throws RequestNotAvailableException
     */
    public function send(User $client, array $data): DistressSignal
    {
        $this->ensureCanSend($client);

        $recipients = $this->search->interveningAt($data['latitude'], $data['longitude'], $client->getKey());

        if ($recipients->isEmpty()) {
            throw new RequestNotAvailableException(
                'Aucun garage n’intervient à votre position pour le moment. Cherchez un garage proche sur la carte.',
                'no_recipient',
                422,
            );
        }

        $signal = DB::transaction(function () use ($client, $data, $recipients): DistressSignal {
            $signal = DistressSignal::create([
                'public_id' => (string) Str::uuid(),
                'client_user_id' => $client->getKey(),
                'message' => $data['message'],
                'service_id' => $data['service_id'] ?? null,
                'vehicle_type_id' => $data['vehicle_type_id'] ?? null,
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'status' => DistressSignalStatus::Open,
                'expires_at' => now()->addMinutes($this->settings->distressSignalWaitMinutes()),
            ]);

            $signal->recipients()->createMany($recipients->map(fn (Business $business) => [
                'business_id' => $business->getKey(),
                'distance_m' => (int) round($business->distance_m),
            ])->all());

            return $signal;
        });

        foreach ($recipients as $business) {
            Notification::send($business->responders(), new DistressSignalReceived($signal, (int) round($business->distance_m)));
        }

        return $signal;
    }

    /**
     * Le premier garage qui accepte est débité et reçoit la position et le
     * numéro du client ; le client reçoit le numéro du garage.
     *
     * @throws RequestNotAvailableException
     * @throws InsufficientBalanceException
     */
    public function accept(DistressSignal $signal, Business $business, User $acceptedBy): DistressSignal
    {
        $accepted = DB::transaction(function () use ($signal, $business, $acceptedBy): DistressSignal {
            $locked = DistressSignal::query()->lockForUpdate()->findOrFail($signal->getKey());

            if (! $locked->isOpen()) {
                throw new RequestNotAvailableException(
                    'Ce signal a déjà été pris en charge ou n’est plus actif.',
                    'not_available',
                );
            }

            if (! $business->isVisible()) {
                throw RequestNotAvailableException::membershipRequired();
            }

            $price = $this->pricing->acceptancePriceXof();
            $entry = $this->wallets->debit(
                $business->owner,
                $price,
                WalletEntryType::RequestAcceptance,
                "distress_signal:{$locked->getKey()}:accept",
                'Signal de détresse accepté'.($acceptedBy->isTeamMember() ? " par {$acceptedBy->name}" : ''),
            );

            $locked->update([
                'status' => DistressSignalStatus::Accepted,
                'accepted_business_id' => $business->getKey(),
                'accepted_by_user_id' => $acceptedBy->getKey(),
                'accepted_at' => now(),
                'closed_at' => now(),
                'price_xof' => $price,
                'wallet_entry_id' => $entry?->getKey(),
            ]);
            $locked->recipients()->where('business_id', $business->getKey())->update(['declined_at' => null]);

            BusinessContact::query()->firstOrCreate(
                ['business_id' => $business->getKey(), 'client_user_id' => $locked->client_user_id],
                ['source' => ContactSource::DistressSignal],
            );

            return $locked;
        });

        $accepted->client->notify(new DistressSignalAccepted($accepted));

        return $accepted;
    }

    /**
     * Quand tous les garages alertés ont refusé, le client est prévenu sans attendre la fin du délai.
     */
    public function decline(DistressSignal $signal, Business $business): DistressSignal
    {
        $signal->recipients()->where('business_id', $business->getKey())->update(['declined_at' => now()]);

        $isLeftWithoutRecipient = $signal->isOpen()
            && ! $signal->recipients()->whereNull('declined_at')->exists();

        if ($isLeftWithoutRecipient) {
            $this->close($signal, DistressSignalStatus::Expired);
        }

        return $signal->refresh();
    }

    public function cancel(DistressSignal $signal): DistressSignal
    {
        if ($signal->isOpen()) {
            $signal->update(['status' => DistressSignalStatus::Cancelled, 'closed_at' => now()]);
        }

        return $signal;
    }

    /**
     * Tâche planifiée : signaux arrivés au bout du délai d'attente.
     */
    public function expireDue(): int
    {
        $expired = 0;

        DistressSignal::query()
            ->where('status', DistressSignalStatus::Open)
            ->where('expires_at', '<=', now())
            ->lazyById()
            ->each(function (DistressSignal $signal) use (&$expired): void {
                $this->close($signal, DistressSignalStatus::Expired);
                $expired++;
            });

        return $expired;
    }

    /**
     * @throws RequestNotAvailableException
     */
    private function ensureCanSend(User $client): void
    {
        if ($client->phone === null) {
            throw RequestNotAvailableException::phoneRequired();
        }

        $hasOpenSignal = DistressSignal::query()
            ->where('client_user_id', $client->getKey())
            ->where('status', DistressSignalStatus::Open)
            ->where('expires_at', '>', now())
            ->exists();

        if ($hasOpenSignal) {
            throw new RequestNotAvailableException('Vous avez déjà un signal de détresse en cours.', 'already_open');
        }

        $sentToday = DistressSignal::query()
            ->where('client_user_id', $client->getKey())
            ->where('created_at', '>=', now()->subDay())
            ->count();

        if ($sentToday >= config('findsolux.requests.max_distress_signals_per_day')) {
            throw new RequestNotAvailableException(
                'Vous avez atteint le nombre maximal de signaux de détresse pour aujourd’hui.',
                'daily_limit',
                429,
            );
        }
    }

    private function close(DistressSignal $signal, DistressSignalStatus $status): void
    {
        $closed = DB::transaction(function () use ($signal, $status): bool {
            $locked = DistressSignal::query()->lockForUpdate()->findOrFail($signal->getKey());

            if ($locked->status !== DistressSignalStatus::Open) {
                return false;
            }

            $locked->update(['status' => $status, 'closed_at' => now()]);

            return true;
        });

        if ($closed && $status === DistressSignalStatus::Expired) {
            $signal->refresh()->client->notify(new DistressSignalUnanswered($signal));
        }
    }
}
