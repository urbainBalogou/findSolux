<?php

namespace App\Services\Requests;

use App\Enums\ContactRequestStatus;
use App\Enums\ContactSource;
use App\Enums\WalletEntryType;
use App\Models\Business;
use App\Models\BusinessContact;
use App\Models\ContactRequest;
use App\Models\User;
use App\Notifications\ContactRequestAnswered;
use App\Notifications\ContactRequestReceived;
use App\Services\Offers\RequestPricing;
use App\Services\Settings\PlatformSettings;
use App\Services\Wallets\InsufficientBalanceException;
use App\Services\Wallets\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Demande de contact depuis la fiche d'un garage (C4). Le garage qui accepte
 * est débité une seule fois par client : le contact est ensuite enregistré.
 */
class ContactRequestService
{
    public function __construct(
        private readonly PlatformSettings $settings,
        private readonly RequestPricing $pricing,
        private readonly WalletService $wallets,
    ) {}

    /**
     * Une demande déjà en attente pour ce garage est renvoyée telle quelle.
     *
     * @return array{0: ContactRequest, 1: bool} La demande, et si elle vient d'être créée.
     *
     * @throws RequestNotAvailableException
     */
    public function request(User $client, Business $business, ?string $message): array
    {
        if ($business->owner_user_id === $client->getKey()) {
            throw new RequestNotAvailableException('C’est votre propre garage.', 'own_business', 422);
        }

        if ($client->phone === null) {
            throw RequestNotAvailableException::phoneRequired();
        }

        if ($business->contacts()->where('client_user_id', $client->getKey())->exists()) {
            throw new RequestNotAvailableException(
                'Vous êtes déjà en contact avec ce garage : son numéro est dans « Mes garages ».',
                'already_in_contact',
            );
        }

        $pending = ContactRequest::query()
            ->whereBelongsTo($business)
            ->where('client_user_id', $client->getKey())
            ->where('status', ContactRequestStatus::Pending)
            ->where('expires_at', '>', now())
            ->first();

        if ($pending !== null) {
            return [$pending, false];
        }

        $contactRequest = ContactRequest::create([
            'public_id' => (string) Str::uuid(),
            'client_user_id' => $client->getKey(),
            'business_id' => $business->getKey(),
            'message' => $message,
            'status' => ContactRequestStatus::Pending,
            'expires_at' => now()->addHours($this->settings->contactRequestWaitHours()),
        ]);

        Notification::send($business->responders(), new ContactRequestReceived($contactRequest));

        return [$contactRequest, true];
    }

    /**
     * @throws RequestNotAvailableException
     * @throws InsufficientBalanceException
     */
    public function accept(ContactRequest $contactRequest, Business $business, User $acceptedBy): ContactRequest
    {
        $accepted = DB::transaction(function () use ($contactRequest, $business, $acceptedBy): ContactRequest {
            $locked = ContactRequest::query()->lockForUpdate()->findOrFail($contactRequest->getKey());

            if (! $locked->isPending()) {
                throw RequestNotAvailableException::alreadyAnswered();
            }

            if (! $business->isVisible()) {
                throw RequestNotAvailableException::membershipRequired();
            }

            // Contact déjà échangé entre-temps (signal de détresse) : rien à débiter.
            $isAlreadyInContact = $business->contacts()->where('client_user_id', $locked->client_user_id)->exists();
            $price = $isAlreadyInContact ? 0 : $this->pricing->acceptancePriceXof();
            $entry = $this->wallets->debit(
                $business->owner,
                $price,
                WalletEntryType::RequestAcceptance,
                "contact_request:{$locked->getKey()}:accept",
                'Demande de contact acceptée'.($acceptedBy->isTeamMember() ? " par {$acceptedBy->name}" : ''),
            );

            $locked->update([
                'status' => ContactRequestStatus::Accepted,
                'responded_at' => now(),
                'accepted_by_user_id' => $acceptedBy->getKey(),
                'price_xof' => $price,
                'wallet_entry_id' => $entry?->getKey(),
            ]);

            BusinessContact::query()->firstOrCreate(
                ['business_id' => $business->getKey(), 'client_user_id' => $locked->client_user_id],
                ['source' => ContactSource::ContactRequest],
            );

            return $locked;
        });

        $accepted->client->notify(new ContactRequestAnswered($accepted));

        return $accepted;
    }

    /**
     * @throws RequestNotAvailableException
     */
    public function decline(ContactRequest $contactRequest): ContactRequest
    {
        if (! $contactRequest->isPending()) {
            throw RequestNotAvailableException::alreadyAnswered();
        }

        $contactRequest->update(['status' => ContactRequestStatus::Declined, 'responded_at' => now()]);
        $contactRequest->client->notify(new ContactRequestAnswered($contactRequest));

        return $contactRequest;
    }

    public function cancel(ContactRequest $contactRequest): ContactRequest
    {
        if ($contactRequest->isPending()) {
            $contactRequest->update(['status' => ContactRequestStatus::Cancelled, 'responded_at' => now()]);
        }

        return $contactRequest;
    }

    /**
     * Tâche planifiée : demandes restées sans réponse.
     */
    public function expireDue(): int
    {
        $expired = 0;

        ContactRequest::query()
            ->where('status', ContactRequestStatus::Pending)
            ->where('expires_at', '<=', now())
            ->with(['client', 'business'])
            ->lazyById()
            ->each(function (ContactRequest $contactRequest) use (&$expired): void {
                $contactRequest->update(['status' => ContactRequestStatus::Expired]);
                $contactRequest->client->notify(new ContactRequestAnswered($contactRequest));
                $expired++;
            });

        return $expired;
    }
}
