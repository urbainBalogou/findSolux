<?php

namespace App\Services\Push;

/**
 * Envoi des notifications push (ADR-016). Pilotes : `log`, `array`, `fcm`.
 */
interface PushSender
{
    /**
     * @param  list<string>  $tokens  Jetons des appareils du destinataire.
     */
    public function send(array $tokens, PushMessage $message): void;
}
