<?php

namespace App\Notifications;

use App\Models\ContactRequest;
use App\Notifications\Channels\PushChannel;
use App\Services\Push\PushMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Au garagiste : un client demande ses coordonnées depuis sa fiche.
 */
class ContactRequestReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ContactRequest $contactRequest)
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
        $clientName = $this->contactRequest->client->name ?: 'Un client';

        return new PushMessage(
            'Nouvelle demande de contact',
            $this->contactRequest->message
                ? "{$clientName} : {$this->contactRequest->message}"
                : "{$clientName} souhaite vous contacter.",
            ['type' => 'contact_request', 'id' => $this->contactRequest->public_id],
        );
    }
}
