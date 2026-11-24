<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Garage alerté par un signal de détresse.
 */
#[Fillable(['distress_signal_id', 'business_id', 'distance_m', 'declined_at'])]
class DistressSignalRecipient extends Model
{
    /** @return BelongsTo<DistressSignal, $this> */
    public function distressSignal(): BelongsTo
    {
        return $this->belongsTo(DistressSignal::class);
    }

    /** @return BelongsTo<Business, $this> */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    protected function casts(): array
    {
        return [
            'distance_m' => 'integer',
            'declined_at' => 'immutable_datetime',
        ];
    }
}
