<?php

namespace App\Models;

use App\Enums\CommercialOfferType;
use App\Enums\DurationUnit;
use Database\Factories\CommercialOfferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'type', 'name', 'quantity', 'price_xof', 'duration_value', 'duration_unit',
    'is_unlimited', 'is_active', 'position',
])]
class CommercialOffer extends Model
{
    /** @use HasFactory<CommercialOfferFactory> */
    use HasFactory;

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
            'type' => CommercialOfferType::class,
            'quantity' => 'integer',
            'price_xof' => 'integer',
            'duration_value' => 'integer',
            'duration_unit' => DurationUnit::class,
            'is_unlimited' => 'boolean',
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }
}
