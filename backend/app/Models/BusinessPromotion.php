<?php

namespace App\Models;

use App\Enums\DurationUnit;
use Database\Factories\BusinessPromotionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'business_id', 'position', 'starts_at', 'ends_at', 'duration_value',
    'duration_unit', 'is_unlimited',
])]
class BusinessPromotion extends Model
{
    /** @use HasFactory<BusinessPromotionFactory> */
    use HasFactory;

    /** @return BelongsTo<Business, $this> */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('starts_at', '<=', now())
            ->where(fn (Builder $period) => $period->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    public function durationLabel(): string
    {
        if ($this->is_unlimited) {
            return 'Sans limite';
        }

        return $this->duration_value.' '.$this->duration_unit->label($this->duration_value);
    }

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'duration_value' => 'integer',
            'duration_unit' => DurationUnit::class,
            'is_unlimited' => 'boolean',
        ];
    }
}
