<?php

namespace App\Models;

use App\Enums\DistressSignalStatus;
use Database\Factories\DistressSignalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Signal de détresse d'un client (C5), envoyé aux garages dont la zone
 * d'intervention couvre sa position.
 */
#[Fillable([
    'public_id', 'client_user_id', 'message', 'service_id', 'vehicle_type_id', 'latitude',
    'longitude', 'status', 'expires_at', 'accepted_business_id', 'accepted_at', 'price_xof',
    'wallet_entry_id', 'closed_at', 'accepted_by_user_id',
])]
class DistressSignal extends Model
{
    /** @use HasFactory<DistressSignalFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_user_id');
    }

    /** @return BelongsTo<Business, $this> */
    public function acceptedBusiness(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'accepted_business_id');
    }

    /**
     * Garagiste ou membre de son équipe qui a accepté.
     *
     * @return BelongsTo<User, $this>
     */
    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by_user_id');
    }

    /** @return HasMany<DistressSignalRecipient, $this> */
    public function recipients(): HasMany
    {
        return $this->hasMany(DistressSignalRecipient::class);
    }

    /**
     * Ouvert et pas encore échu (la tâche planifiée peut ne pas être passée).
     */
    public function isOpen(): bool
    {
        return $this->status === DistressSignalStatus::Open && $this->expires_at->isFuture();
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'status' => DistressSignalStatus::class,
            'expires_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
            'price_xof' => 'integer',
        ];
    }
}
