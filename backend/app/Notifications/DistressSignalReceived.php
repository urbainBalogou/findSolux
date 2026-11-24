<?php

namespace App\Notifications;

use App\Models\DistressSignal;
use App\Notifications\Channels\PushChannel;
use App\Services\Push\PushMessage;
use App\Support\Distance;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Au garagiste : un client en panne se trouve dans sa zone d'intervention.
 */
class DistressSignalReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public DistressSignal $signal, public int $distanceM)
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
            'Signal de détresse à '.Distance::format($this->distanceM),
            $this->signal->message,
            ['type' => 'distress_signal', 'id' => $this->signal->public_id],
        );
    }
}
