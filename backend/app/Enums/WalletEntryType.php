<?php

namespace App\Enums;

enum WalletEntryType: string
{
    case TopUp = 'top_up';
    case TopUpRefund = 'top_up_refund';
    /** Achat ou renouvellement d'une offre (adhésion, pack). */
    case Purchase = 'purchase';
    /** Acceptation d'un signal de détresse ou d'une demande de contact. */
    case RequestAcceptance = 'request_acceptance';
    case Refund = 'refund';
    case Adjustment = 'adjustment';
}
