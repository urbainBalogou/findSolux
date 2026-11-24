<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    /** En cours, ou programmée (prolongation d'une adhésion qui commence plus tard). */
    case Active = 'active';
    case Expired = 'expired';
    /** Remplacée par son renouvellement. */
    case Renewed = 'renewed';
}
