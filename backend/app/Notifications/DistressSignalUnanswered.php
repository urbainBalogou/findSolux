<?php

namespace App\Notifications;

use App\Models\DistressSignal;
use App\Notifications\Channels\PushChannel;
use App\Services\Push\PushMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Au client : aucun garage n'a accepté son signal de détresse à temps.
 */
class DistressSignalUnanswered extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public DistressSignal $signal)
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
            'Aucun garage disponible pour le moment',
            'Votre signal n’a pas été pris en charge. Vous pouvez en envoyer un nouveau.',
            ['type' => 'distress_signal_unanswered', 'id' => $this->signal->public_id],
        );
    }
}
