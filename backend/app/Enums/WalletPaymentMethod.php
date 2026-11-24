<?php

namespace App\Enums;

enum WalletPaymentMethod: string
{
    case MoovTogo = 'moov_tg';
    case TMoney = 'togocel';

    public function label(): string
    {
        return match ($this) {
            self::MoovTogo => 'Flooz (Moov Africa)',
            self::TMoney => 'TMoney (Yas Togo)',
        };
    }
}
