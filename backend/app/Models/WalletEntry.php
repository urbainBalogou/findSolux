<?php

namespace App\Models;

use App\Enums\WalletEntryDirection;
use App\Enums\WalletEntryType;
use Database\Factories\WalletEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'wallet_id', 'type', 'direction', 'amount_xof', 'balance_after_xof',
    'reference', 'description',
])]
class WalletEntry extends Model
{
    /** @use HasFactory<WalletEntryFactory> */
    use HasFactory;

    /** @return BelongsTo<Wallet, $this> */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    protected function casts(): array
    {
        return [
            'type' => WalletEntryType::class,
            'direction' => WalletEntryDirection::class,
            'amount_xof' => 'integer',
            'balance_after_xof' => 'integer',
        ];
    }
}
