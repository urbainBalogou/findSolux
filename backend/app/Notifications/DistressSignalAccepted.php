<?php

namespace App\Notifications;

use App\Models\DistressSignal;
use App\Notifications\Channels\PushChannel;
use App\Services\Push\PushMessage;
use App\Support\PhoneNumber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Au client : un garage a accepté son signal de détresse.
 */
class DistressSignalAccepted extends Notification implements ShouldQueue
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
        $business = $this->signal->acceptedBusiness;

        return new PushMessage(
            "{$business->name} a accepté votre demande d’aide",
            'Appelez le garage au '.PhoneNumber::display($business->phone).'.',
            ['type' => 'distress_signal_accepted', 'id' => $this->signal->public_id],
        );
    }
}
