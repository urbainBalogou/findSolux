<?php

namespace App\Services\Otp;

interface OtpSender
{
    /**
     * Canal enregistré avec chaque code envoyé (sms, whatsapp…).
     */
    public function channel(): string;

    public function send(string $phone, string $code): void;
}
