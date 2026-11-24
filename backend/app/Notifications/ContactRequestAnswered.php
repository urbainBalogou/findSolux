<?php

namespace App\Notifications;

use App\Enums\ContactRequestStatus;
use App\Models\ContactRequest;
use App\Notifications\Channels\PushChannel;
use App\Services\Push\PushMessage;
use App\Support\PhoneNumber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Au client : sa demande de contact est acceptée, refusée ou restée sans réponse.
 */
class ContactRequestAnswered extends Notification implements ShouldQueue
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
        $business = $this->contactRequest->business;

        [$title, $body] = match ($this->contactRequest->status) {
            ContactRequestStatus::Accepted => [
                "{$business->name} accepte d’être contacté",
                'Son numéro : '.PhoneNumber::display($business->phone).'. Il est enregistré dans « Mes garages ».',
            ],
            ContactRequestStatus::Declined => [
                "{$business->name} n’est pas disponible",
                'Essayez un autre garage proche ou envoyez un signal de détresse.',
            ],
            default => [
                "{$business->name} n’a pas répondu",
                'Votre demande a expiré. Vous pouvez la renouveler ou essayer un autre garage.',
            ],
        };

        return new PushMessage($title, $body, [
            'type' => 'contact_request_answered',
            'id' => $this->contactRequest->public_id,
            'business_slug' => $business->slug,
            'status' => $this->contactRequest->status->value,
        ]);
    }
}
