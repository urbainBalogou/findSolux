<?php

namespace App\Services\Push\Senders;

use App\Services\Push\PushMessage;
use App\Services\Push\PushSender;
use Illuminate\Support\Facades\Log;

/**
 * Développement : la notification est écrite dans le journal.
 */
class LogPushSender implements PushSender
{
    public function send(array $tokens, PushMessage $message): void
    {
        Log::info("[push] {$message->title} — {$message->body}", [
            'tokens' => count($tokens),
            'data' => $message->data,
        ]);
    }
}
