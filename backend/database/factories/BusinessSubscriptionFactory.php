<?php

namespace Database\Factories;

use App\Enums\CommercialOfferType;
use App\Enums\DurationUnit;
use App\Enums\SubscriptionSource;
use App\Enums\SubscriptionStatus;
use App\Models\Business;
use App\Models\BusinessSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Adhésion d'un mois en cours, achetée depuis le portefeuille. Les droits de
 * la fiche ne sont pas recalculés : appeler BusinessEntitlements::sync().
 *
 * @extends Factory<BusinessSubscription>
 */
class BusinessSubscriptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'offer_type' => CommercialOfferType::Membership,
            'name' => 'Adhésion mensuelle',
            'quantity' => null,
            'price_xof' => 5000,
            'duration_value' => 1,
            'duration_unit' => DurationUnit::Month,
            'is_unlimited' => false,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'status' => SubscriptionStatus::Active,
            'source' => SubscriptionSource::Wallet,
            'auto_renew' => false,
        ];
    }

    public function serviceBundle(int $quantity): static
    {
        return $this->state([
            'offer_type' => CommercialOfferType::ServiceBundle,
            'name' => "Pack {$quantity} services",
            'quantity' => $quantity,
        ]);
    }

    public function endingAt(mixed $endsAt): static
    {
        return $this->state(['ends_at' => $endsAt]);
    }
}
