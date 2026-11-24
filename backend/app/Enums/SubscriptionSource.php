<?php

namespace App\Enums;

enum SubscriptionSource: string
{
    /** Achetée par le garagiste, débitée de son portefeuille. */
    case Wallet = 'wallet';
    /** Renouvelée automatiquement, débitée de son portefeuille. */
    case Renewal = 'renewal';
    /** Accordée gratuitement depuis le back-office (offre de lancement…). */
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Wallet => 'Achat',
            self::Renewal => 'Renouvellement',
            self::Admin => 'Offerte',
        };
    }
}
