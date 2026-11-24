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
            // Visible des clients : adhésion en cours (droits recopiés, ADR-015).
            'membership_ends_at' => now()->addMonth(),
        ];
    }

    /**
     * Sans adhésion : fiche invisible, aucune demande reçue.
     */
    public function withoutMembership(): static
    {
        return $this->state(['membership_ends_at' => null, 'has_unlimited_membership' => false]);
    }

    /**
     * Garagiste qui se déplace jusqu'à ce rayon (destinataire des signaux de détresse).
     */
    public function travelling(int $radiusKm = 10): static
    {
        return $this->state(['is_mobile' => true, 'service_radius_km' => $radiusKm]);
    }

    /**
     * Packs « Employés et répondeurs » couvrant ce nombre de membres de l'équipe.
     */
    public function withTeamQuota(int $quota): static
    {
        return $this->state(['team_quota' => $quota]);
    }
}
