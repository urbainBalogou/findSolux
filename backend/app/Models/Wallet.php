<?php

namespace App\Models;

use Database\Factories\WalletFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'balance_xof'])]
class Wallet extends Model
{
    /** @use HasFactory<WalletFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<WalletEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(WalletEntry::class);
    }

    /** @return HasMany<WalletTopUp, $this> */
    public function topUps(): HasMany
    {
        return $this->hasMany(WalletTopUp::class);
    }

    protected function casts(): array
    {
        return ['balance_xof' => 'integer'];
    }
}
