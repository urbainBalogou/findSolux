<?php

namespace App\Models;

use App\Enums\WalletPaymentMethod;
use App\Enums\WalletTopUpStatus;
use Database\Factories\WalletTopUpFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'public_id', 'wallet_id', 'amount_xof', 'status', 'payment_method',
    'provider', 'provider_transaction_id', 'provider_status', 'failure_reason',
    'completed_at', 'expires_at',
])]
class WalletTopUp extends Model
{
    /** @use HasFactory<WalletTopUpFactory> */
    use HasFactory;

    /** @return BelongsTo<Wallet, $this> */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    protected function casts(): array
    {
        return [
            'amount_xof' => 'integer',
            'status' => WalletTopUpStatus::class,
            'payment_method' => WalletPaymentMethod::class,
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
