<?php

namespace App\Services\Push\Senders;

use App\Services\Push\PushMessage;
use App\Services\Push\PushSender;

/**
 * Tests : les notifications sont gardées en mémoire.
 */
class ArrayPushSender implements PushSender
{
    /** @var list<array{tokens: list<string>, message: PushMessage}> */
    public array $sent = [];

    public function send(array $tokens, PushMessage $message): void
    {
        $this->sent[] = ['tokens' => $tokens, 'message' => $message];
    }
}
