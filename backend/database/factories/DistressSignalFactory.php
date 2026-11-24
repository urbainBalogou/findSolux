<?php

namespace Database\Factories;

use App\Enums\DistressSignalStatus;
use App\Models\DistressSignal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Signal ouvert à Lomé (centre), sans destinataires.
 *
 * @extends Factory<DistressSignal>
 */
class DistressSignalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_id' => fn () => (string) Str::uuid(),
            'client_user_id' => User::factory(),
            'message' => 'Crevaison du pneu avant',
            'latitude' => 6.1319,
            'longitude' => 1.2228,
            'status' => DistressSignalStatus::Open,
            'expires_at' => now()->addMinutes(10),
        ];
    }
}
