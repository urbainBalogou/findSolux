<?php

namespace App\Models;

use App\Models\Concerns\HasActiveScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'country_code', 'latitude', 'longitude', 'is_active'])]
class City extends Model
{
    use HasActiveScope;

    /**
     * @return HasMany<District, $this>
     */
    public function districts(): HasMany
    {
        return $this->hasMany(District::class);
    }

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'is_active' => 'boolean',
        ];
    }
}
