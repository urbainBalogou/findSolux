<?php

namespace App\Notifications\Channels;

use App\Services\Push\PushMessage;
use App\Services\Push\PushSender;
use Illuminate\Notifications\Notification;

/**
 * Canal « push » des notifications : la notification fournit `toPush()`, le
 * destinataire `routeNotificationForPush()` (jetons de ses appareils).
 */
class PushChannel
{
    public function __construct(private readonly PushSender $sender) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $tokens = $notifiable->routeNotificationFor('push', $notification);

        if (! is_array($tokens) || $tokens === [] || ! method_exists($notification, 'toPush')) {
            return;
        }

        /** @var PushMessage $message */
        $message = $notification->toPush($notifiable);

        $this->sender->send(array_values($tokens), $message);
    }
}
