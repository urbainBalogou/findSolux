<?php

namespace Database\Factories;

use App\Enums\DurationUnit;
use App\Models\Business;
use App\Models\BusinessPromotion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessPromotion>
 */
class BusinessPromotionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'position' => 1,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'duration_value' => 1,
            'duration_unit' => DurationUnit::Month,
            'is_unlimited' => false,
        ];
    }
}
