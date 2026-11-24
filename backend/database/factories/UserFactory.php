<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'google_id' => fake()->unique()->numerify('1##################'),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+2289'.fake()->unique()->numerify('#######'),
            'name' => fake()->name(),
        ];
    }

    /**
     * Compte qui n'a pas encore saisi le numéro où le joindre.
     */
    public function withoutPhone(): static
    {
        return $this->state(fn (array $attributes) => ['phone' => null]);
    }

    /**
     * Membre de l'équipe d'un garage : identifiant et mot de passe « motdepasse »,
     * sans compte Google ni numéro.
     */
    public function teamMember(Business $business): static
    {
        return $this->state(fn (array $attributes) => [
            'team_business_id' => $business->getKey(),
            'username' => fake()->unique()->userName(),
            'password' => 'motdepasse',
            'google_id' => null,
            'email' => null,
            'phone' => null,
        ]);
    }
}
