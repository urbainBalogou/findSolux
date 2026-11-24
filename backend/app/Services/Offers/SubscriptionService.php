<?php

namespace App\Services\Offers;

use App\Enums\CommercialOfferType;
use App\Enums\SubscriptionSource;
use App\Enums\SubscriptionStatus;
use App\Enums\WalletEntryType;
use App\Models\Admin;
use App\Models\Business;
use App\Models\BusinessSubscription;
use App\Models\CommercialOffer;
use App\Notifications\SubscriptionEndingSoon;
use App\Notifications\SubscriptionExpired;
use App\Notifications\SubscriptionRenewed;
use App\Services\Wallets\InsufficientBalanceException;
use App\Services\Wallets\WalletService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Achat, octroi, renouvellement et échéance des offres d'un garage (§4.3, ADR-015).
 */
class SubscriptionService
{
    public function __construct(
        private readonly WalletService $wallets,
        private readonly BusinessEntitlements $entitlements,
    ) {}

    /**
     * Achat par le garagiste, débité de son portefeuille.
     *
     * @throws OfferNotAvailableException
     * @throws InsufficientBalanceException
     */
    public function purchase(Business $business, CommercialOffer $offer, bool $autoRenew): BusinessSubscription
    {
        if (! $offer->is_active || ! $offer->type->isPurchasable()) {
            throw new OfferNotAvailableException('Cette offre n’est pas disponible à l’achat.');
        }

        return DB::transaction(function () use ($business, $offer, $autoRenew): BusinessSubscription {
            // Deux achats simultanés d'adhésion calculeraient la même date de début.
            Business::query()->lockForUpdate()->findOrFail($business->getKey());

            $subscription = $business->subscriptions()->create([
                ...$this->termsOf($offer, $this->startFor($business, $offer)),
                'source' => SubscriptionSource::Wallet,
                'auto_renew' => $autoRenew && ! $offer->is_unlimited,
            ]);
            $this->debitFor($business, $subscription, "Achat : {$offer->name}");
            $this->entitlements->sync($business);

            return $subscription->refresh();
        });
    }

    /**
     * Octroi gratuit depuis le back-office (offre de lancement, geste commercial).
     *
     * @throws OfferNotAvailableException
     */
    public function grant(Business $business, CommercialOffer $offer, Admin $admin): BusinessSubscription
    {
        if (! $offer->type->isPurchasable()) {
            throw new OfferNotAvailableException('Cette offre ne peut pas être accordée.');
        }

        return DB::transaction(function () use ($business, $offer, $admin): BusinessSubscription {
            Business::query()->lockForUpdate()->findOrFail($business->getKey());

            $subscription = $business->subscriptions()->create([
                ...$this->termsOf($offer, $this->startFor($business, $offer)),
                'price_xof' => 0,
                'source' => SubscriptionSource::Admin,
                'granted_by_admin_id' => $admin->getKey(),
            ]);
            $this->entitlements->sync($business);

            return $subscription;
        });
    }

    /**
     * @throws OfferNotAvailableException
     */
    public function setAutoRenew(BusinessSubscription $subscription, bool $autoRenew): BusinessSubscription
    {
        if ($subscription->is_unlimited || $subscription->status !== SubscriptionStatus::Active) {
            throw new OfferNotAvailableException('Cette offre ne peut pas être renouvelée.');
        }

        $subscription->update(['auto_renew' => $autoRenew]);

        return $subscription;
    }

    /**
     * Tâche planifiée : renouvellements, échéances, puis rappels.
     *
     * @return array{renewed: int, expired: int, reminded: int}
     */
    public function processDue(): array
    {
        return [
            'renewed' => $this->renewDue(),
            'expired' => $this->expireDue(),
            'reminded' => $this->sendReminders(),
        ];
    }

    /**
     * Renouvelle, un peu avant l'échéance, les offres dont le renouvellement
     * automatique est activé. Faute de solde, l'essai est refait au passage
     * suivant, jusqu'à l'échéance.
     */
    public function renewDue(): int
    {
        $renewed = 0;
        $lead = now()->addMinutes((int) config('findsolux.subscriptions.renewal_lead_minutes'));

        BusinessSubscription::query()
            ->where('status', SubscriptionStatus::Active)
            ->where('auto_renew', true)
            ->where('is_unlimited', false)
            ->where('ends_at', '<=', $lead)
            ->with(['business.owner', 'commercialOffer'])
            ->lazyById()
            ->each(function (BusinessSubscription $subscription) use (&$renewed): void {
                if ($this->renew($subscription)) {
                    $renewed++;
                }
            });

        return $renewed;
    }

    /**
     * Offres arrivées à échéance : la fiche perd les droits correspondants,
     * sans perte de données. Le garagiste est prévenu, sauf si une autre offre
     * prend le relais.
     */
    public function expireDue(): int
    {
        $expired = 0;

        BusinessSubscription::query()
            ->where('status', SubscriptionStatus::Active)
            ->where('is_unlimited', false)
            ->where('ends_at', '<=', now())
            ->with(['business.owner', 'commercialOffer'])
            ->lazyById()
            ->each(function (BusinessSubscription $subscription) use (&$expired): void {
                $wasRenewed = BusinessSubscription::query()->where('renewed_from_id', $subscription->getKey())->exists();
                $subscription->update([
                    'status' => $wasRenewed ? SubscriptionStatus::Renewed : SubscriptionStatus::Expired,
                ]);
                $this->entitlements->sync($subscription->business);

                if (! $wasRenewed && ! $this->hasSuccessor($subscription)) {
                    $subscription->business->owner->notify(new SubscriptionExpired(
                        $subscription,
                        $this->renewalFailureReason($subscription),
                    ));
                    $expired++;
                }
            });

        return $expired;
    }

    /**
     * Rappels 7 jours puis 1 jour avant l'échéance (une seule fois chacun).
     */
    public function sendReminders(): int
    {
        $reminded = 0;

        foreach ([1 => 'reminded_1_day_at', 7 => 'reminded_7_days_at'] as $days => $column) {
            BusinessSubscription::query()
                ->where('status', SubscriptionStatus::Active)
                ->where('is_unlimited', false)
                ->whereNull('reminded_1_day_at')
                ->whereNull($column)
                ->where('ends_at', '>', now())
                ->where('ends_at', '<=', now()->addDays($days))
                ->with(['business.owner', 'commercialOffer'])
                ->lazyById()
                ->each(function (BusinessSubscription $subscription) use ($days, &$reminded): void {
                    // Le rappel J-1 rend le rappel J-7 sans objet.
                    $subscription->update($days === 1
                        ? ['reminded_1_day_at' => now(), 'reminded_7_days_at' => $subscription->reminded_7_days_at ?? now()]
                        : ['reminded_7_days_at' => now()]);

                    $isShorterThanNotice = $subscription->starts_at->gt($subscription->ends_at->subDays($days));

                    if ($isShorterThanNotice || $this->hasSuccessor($subscription)) {
                        return;
                    }

                    $subscription->business->owner->notify(
                        new SubscriptionEndingSoon($subscription, $days, $this->willRenew($subscription)),
                    );
                    $reminded++;
                });
        }

        return $reminded;
    }

    /**
     * Le renouvellement commence à l'échéance de l'offre renouvelée, qui reste
     * active jusque-là : la fiche ne perd jamais ses droits.
     */
    private function renew(BusinessSubscription $subscription): bool
    {
        $successor = $this->membershipSuccessor($subscription);

        if ($successor !== null) {
            // Le garagiste a déjà prolongé son adhésion : le réglage passe à la prolongation.
            $successor->update(['auto_renew' => true]);
            $subscription->update(['auto_renew' => false]);

            return false;
        }

        $offer = $subscription->commercialOffer;

        if (! $this->isRenewable($offer)) {
            return false;
        }

        try {
            $renewal = DB::transaction(function () use ($subscription, $offer): ?BusinessSubscription {
                $locked = BusinessSubscription::query()->lockForUpdate()->findOrFail($subscription->getKey());

                if ($locked->status !== SubscriptionStatus::Active || ! $locked->auto_renew) {
                    return null;
                }

                $renewal = $subscription->business->subscriptions()->create([
                    ...$this->termsOf($offer, $locked->ends_at),
                    'source' => SubscriptionSource::Renewal,
                    'auto_renew' => ! $offer->is_unlimited,
                    'renewed_from_id' => $locked->getKey(),
                ]);
                $this->debitFor($subscription->business, $renewal, "Renouvellement : {$offer->name}");
                $locked->update(['auto_renew' => false]);
                $this->entitlements->sync($subscription->business);

                return $renewal;
            });
        } catch (InsufficientBalanceException) {
            return false;
        }

        if ($renewal === null) {
            return false;
        }

        $subscription->business->owner->notify(new SubscriptionRenewed($renewal));

        return true;
    }

    private function willRenew(BusinessSubscription $subscription): bool
    {
        return $subscription->auto_renew
            && $this->isRenewable($subscription->commercialOffer)
            && $this->wallets->balanceOf($subscription->business->owner) >= $subscription->commercialOffer->price_xof;
    }

    /**
     * @return 'offer_unavailable'|'insufficient_balance'|null Null : renouvellement non demandé.
     */
    private function renewalFailureReason(BusinessSubscription $subscription): ?string
    {
        if (! $subscription->auto_renew) {
            return null;
        }

        return $this->isRenewable($subscription->commercialOffer) ? 'insufficient_balance' : 'offer_unavailable';
    }

    private function isRenewable(?CommercialOffer $offer): bool
    {
        return $offer !== null && $offer->is_active && $offer->type->isPurchasable();
    }

    private function hasSuccessor(BusinessSubscription $subscription): bool
    {
        return BusinessSubscription::query()->where('renewed_from_id', $subscription->getKey())->exists()
            || $this->membershipSuccessor($subscription) !== null;
    }

    /**
     * Adhésion achetée pour prendre la suite de celle-ci (prolongation).
     */
    private function membershipSuccessor(BusinessSubscription $subscription): ?BusinessSubscription
    {
        if ($subscription->offer_type !== CommercialOfferType::Membership) {
            return null;
        }

        return BusinessSubscription::query()
            ->where('business_id', $subscription->business_id)
            ->where('offer_type', CommercialOfferType::Membership)
            ->where('status', SubscriptionStatus::Active)
            ->whereKeyNot($subscription->getKey())
            ->where('starts_at', '>=', $subscription->starts_at)
            ->where('starts_at', '<=', $subscription->ends_at)
            ->orderBy('starts_at')
            ->first();
    }

    /**
     * Une nouvelle adhésion prolonge l'adhésion en cours ; un pack commence tout de suite.
     *
     * @throws OfferNotAvailableException
     */
    private function startFor(Business $business, CommercialOffer $offer): CarbonImmutable
    {
        $now = CarbonImmutable::now();

        if ($offer->type !== CommercialOfferType::Membership) {
            return $now;
        }

        $memberships = $business->subscriptions()
            ->where('status', SubscriptionStatus::Active)
            ->where('offer_type', CommercialOfferType::Membership)
            ->get();

        if ($memberships->contains(fn (BusinessSubscription $membership) => $membership->is_unlimited)) {
            throw new OfferNotAvailableException('Votre adhésion est déjà illimitée.');
        }

        $latestEnd = $memberships->max('ends_at');

        return $latestEnd !== null && $latestEnd->gt($now) ? $latestEnd : $now;
    }

    /**
     * @return array<string, mixed>
     */
    private function termsOf(CommercialOffer $offer, CarbonImmutable $startsAt): array
    {
        return [
            'commercial_offer_id' => $offer->getKey(),
            'offer_type' => $offer->type,
            'name' => $offer->name,
            'quantity' => $offer->quantity,
            'price_xof' => $offer->price_xof,
            'duration_value' => $offer->duration_value,
            'duration_unit' => $offer->duration_unit,
            'is_unlimited' => $offer->is_unlimited,
            'starts_at' => $startsAt,
            'ends_at' => $offer->is_unlimited ? null : $offer->duration_unit->addTo($startsAt, $offer->duration_value),
            'status' => SubscriptionStatus::Active,
        ];
    }

    /**
     * @throws InsufficientBalanceException
     */
    private function debitFor(Business $business, BusinessSubscription $subscription, string $description): void
    {
        $entry = $this->wallets->debit(
            $business->owner,
            $subscription->price_xof,
            WalletEntryType::Purchase,
            "subscription:{$subscription->getKey()}",
            $description,
        );

        $subscription->update(['wallet_entry_id' => $entry?->getKey()]);
    }
}
