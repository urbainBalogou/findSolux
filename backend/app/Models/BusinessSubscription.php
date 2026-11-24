<?php

namespace App\Models;

use App\Enums\CommercialOfferType;
use App\Enums\DurationUnit;
use App\Enums\SubscriptionSource;
use App\Enums\SubscriptionStatus;
use Carbon\CarbonInterface;
use Database\Factories\BusinessSubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Offre achetée ou accordée à un garage (ADR-015). L'offre du catalogue est
 * recopiée au moment de l'achat.
 */
#[Fillable([
    'business_id', 'commercial_offer_id', 'offer_type', 'name', 'quantity', 'price_xof',
    'duration_value', 'duration_unit', 'is_unlimited', 'starts_at', 'ends_at', 'status',
    'source', 'auto_renew', 'wallet_entry_id', 'granted_by_admin_id', 'renewed_from_id',
    'reminded_7_days_at', 'reminded_1_day_at',
])]
class BusinessSubscription extends Model
{
    /** @use HasFactory<BusinessSubscriptionFactory> */
    use HasFactory;

    /** @return BelongsTo<Business, $this> */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /** @return BelongsTo<CommercialOffer, $this> */
    public function commercialOffer(): BelongsTo
    {
        return $this->belongsTo(CommercialOffer::class);
    }

    /**
     * Offres actives couvrant cet instant (les prolongations programmées plus
     * tard sont exclues).
     */
    public function scopeCurrent(Builder $query, ?CarbonInterface $moment = null): Builder
    {
        $moment ??= now();

        return $query
            ->where('status', SubscriptionStatus::Active)
            ->where('starts_at', '<=', $moment)
            ->where(fn (Builder $period) => $period->where('is_unlimited', true)->orWhere('ends_at', '>', $moment));
    }

    public function isCurrent(?CarbonInterface $moment = null): bool
    {
        $moment ??= now();

        return $this->status === SubscriptionStatus::Active
            && $this->starts_at->lte($moment)
            && ($this->is_unlimited || $this->ends_at->gt($moment));
    }

    protected function casts(): array
    {
        return [
            'offer_type' => CommercialOfferType::class,
            'quantity' => 'integer',
            'price_xof' => 'integer',
            'duration_value' => 'integer',
            'duration_unit' => DurationUnit::class,
            'is_unlimited' => 'boolean',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'status' => SubscriptionStatus::class,
            'source' => SubscriptionSource::class,
            'auto_renew' => 'boolean',
            'reminded_7_days_at' => 'immutable_datetime',
            'reminded_1_day_at' => 'immutable_datetime',
        ];
    }
}
