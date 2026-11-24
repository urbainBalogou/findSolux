<?php

namespace App\Notifications;

use App\Models\BusinessSubscription;
use App\Notifications\Channels\PushChannel;
use App\Services\Push\PushMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Au garagiste : rappel 7 jours puis 1 jour avant l'échéance d'une offre.
 */
class SubscriptionEndingSoon extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public BusinessSubscription $subscription,
        public int $daysBefore,
        public bool $willRenew,
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
        $when = $this->daysBefore === 1 ? 'demain' : "dans {$this->daysBefore} jours";

        $body = match (true) {
            $this->willRenew => 'Elle sera renouvelée automatiquement depuis votre portefeuille.',
            $this->subscription->auto_renew => 'Votre solde ne suffit pas pour le renouvellement automatique : rechargez votre portefeuille.',
            default => 'Renouvelez-la depuis votre portefeuille pour rester visible.',
        };

        return new PushMessage(
            "« {$this->subscription->name} » se termine {$when}",
            $body,
            ['type' => 'subscription_ending', 'id' => (string) $this->subscription->getKey()],
        );
    }
}
