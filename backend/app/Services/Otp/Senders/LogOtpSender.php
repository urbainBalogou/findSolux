<?php

namespace App\Services\Otp\Senders;

use App\Services\Otp\OtpSender;
use Illuminate\Support\Facades\Log;

/**
 * Développement : le code est écrit dans storage/logs/laravel.log.
 */
class LogOtpSender implements OtpSender
{
    public function channel(): string
    {
        return 'log';
    }

    public function send(string $phone, string $code): void
    {
        Log::info("Code de connexion pour {$phone} : {$code}");
    }
}
