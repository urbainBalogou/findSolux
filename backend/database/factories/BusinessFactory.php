<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\District;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Nécessite les référentiels (ReferenceDataSeeder).
 *
 * @extends Factory<Business>
 */
class BusinessFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $district = District::inRandomOrder()->first();
        $name = 'Garage '.fake()->unique()->lastName();

        return [
            'owner_user_id' => User::factory(),
            'trade_id' => fn () => Trade::firstWhere('slug', 'mecanique')->id,
            'name' => $name,
            'slug' => Str::slug("{$name} {$district->name}"),
            'phone' => '+2289'.fake()->numerify('#######'),
            'whatsapp_phone' => fn (array $attributes) => $attributes['phone'],
            'city_id' => $district->city_id,
            'district_id' => $district->id,
            'latitude' => $district->latitude,
            'longitude' => $district->longitude,
        ];
    }
}
