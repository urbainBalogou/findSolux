<?php

namespace App\Notifications;

use App\Models\BusinessSubscription;
use App\Notifications\Channels\PushChannel;
use App\Services\Push\PushMessage;
use App\Support\Price;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Au garagiste : une offre a été renouvelée automatiquement.
 */
class SubscriptionRenewed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public BusinessSubscription $subscription)
    {
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
        return new PushMessage(
            "« {$this->subscription->name} » renouvelée",
            Price::format($this->subscription->price_xof).' débités de votre portefeuille.',
            ['type' => 'subscription_renewed', 'id' => (string) $this->subscription->getKey()],
        );
    }
}
