<?php

namespace App\Notifications;

use App\Enums\CommercialOfferType;
use App\Models\BusinessSubscription;
use App\Notifications\Channels\PushChannel;
use App\Services\Push\PushMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Au garagiste : une offre est arrivée à échéance sans être renouvelée.
 */
class SubscriptionExpired extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  'offer_unavailable'|'insufficient_balance'|null  $renewalFailureReason
     */
    public function __construct(
        public BusinessSubscription $subscription,
        public ?string $renewalFailureReason = null,
    ) {
        $this->afterCommit();
    }

    /**
     * @return list<class-string>
     */
    public function via(object $notifiable): array
    {
        return [PushChannel::class];
    }

    public function toPush(object $notifiable): PushMessage
    {
        $consequence = match ($this->subscription->offer_type) {
            CommercialOfferType::Membership => 'Votre fiche n’est plus visible des clients.',
            CommercialOfferType::ServiceBundle => 'Une partie de vos services n’est plus visible.',
            CommercialOfferType::StaffBundle => 'Une partie de votre équipe ne peut plus répondre aux demandes.',
        };

        $reason = match ($this->renewalFailureReason) {
            'insufficient_balance' => ' Le renouvellement automatique a échoué : solde insuffisant.',
            'offer_unavailable' => ' Cette offre n’est plus proposée : choisissez-en une autre.',
            default => '',
        };

        return new PushMessage(
            "« {$this->subscription->name} » est terminée",
            $consequence.$reason,
            ['type' => 'subscription_expired', 'id' => (string) $this->subscription->getKey()],
        );
    }
}
