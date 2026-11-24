<?php

namespace Database\Factories;

use App\Enums\CommercialOfferType;
use App\Enums\DurationUnit;
use App\Models\CommercialOffer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommercialOffer>
 */
class CommercialOfferFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => CommercialOfferType::Membership,
            'name' => 'Adhésion mensuelle',
            'price_xof' => 5000,
            'duration_value' => 1,
            'duration_unit' => DurationUnit::Month,
            'is_unlimited' => false,
            'is_active' => true,
            'position' => 1,
        ];
    }
}
