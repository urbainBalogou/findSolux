<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Service proposé par un garage, avec un prix indicatif optionnel.
 */
#[Fillable(['service_id', 'vehicle_type_id', 'price_min', 'price_max', 'description'])]
class BusinessService extends Model
{
    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    protected function casts(): array
    {
        return [
            'price_min' => 'integer',
            'price_max' => 'integer',
        ];
    }
}
