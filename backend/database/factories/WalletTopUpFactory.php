<?php

namespace Database\Factories;

use App\Enums\WalletPaymentMethod;
use App\Enums\WalletTopUpStatus;
use App\Models\Wallet;
use App\Models\WalletTopUp;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WalletTopUp>
 */
class WalletTopUpFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_id' => (string) Str::uuid(),
            'wallet_id' => Wallet::factory(),
            'amount_xof' => 5_000,
            'status' => WalletTopUpStatus::Pending,
            'payment_method' => WalletPaymentMethod::MoovTogo,
            'provider' => 'fedapay',
            'expires_at' => now()->addDay(),
        ];
    }
}
