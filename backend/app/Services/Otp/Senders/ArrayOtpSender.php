<?php

namespace App\Services\Otp\Senders;

use App\Services\Otp\OtpSender;

/**
 * Tests : garde en mémoire les codes envoyés.
 */
class ArrayOtpSender implements OtpSender
{
    /** @var array<string, list<string>> */
    private array $sent = [];

    public function channel(): string
    {
        return 'array';
    }

    public function send(string $phone, string $code): void
    {
        $this->sent[$phone][] = $code;
    }

    public function lastCodeFor(string $phone): ?string
    {
        return isset($this->sent[$phone]) ? end($this->sent[$phone]) : null;
    }

    public function sentCount(string $phone): int
    {
        return count($this->sent[$phone] ?? []);
    }
}
