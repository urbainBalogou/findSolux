<?php

namespace App\Enums;

enum WalletTopUpStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    public function isTerminal(): bool
    {
        return $this !== self::Pending;
    }
}
