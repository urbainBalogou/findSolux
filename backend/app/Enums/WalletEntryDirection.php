<?php

namespace App\Enums;

enum WalletEntryDirection: string
{
    case Credit = 'credit';
    case Debit = 'debit';
}
