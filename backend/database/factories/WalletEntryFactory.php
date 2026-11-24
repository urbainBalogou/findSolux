<?php

namespace Database\Factories;

use App\Enums\WalletEntryDirection;
use App\Enums\WalletEntryType;
use App\Models\Wallet;
use App\Models\WalletEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WalletEntry>
 */
class WalletEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'wallet_id' => Wallet::factory(),
            'type' => WalletEntryType::TopUp,
            'direction' => WalletEntryDirection::Credit,
            'amount_xof' => 5_000,
            'balance_after_xof' => 5_000,
            'reference' => fake()->unique()->uuid(),
            'description' => 'Recharge du portefeuille',
        ];
    }
}
