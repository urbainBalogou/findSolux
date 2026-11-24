<?php

namespace Tests\Feature\Offers;

use App\Enums\CommercialOfferType;
use App\Models\Business;
use App\Models\BusinessSubscription;
use App\Models\CommercialOffer;
use App\Models\Service;
use App\Models\User;
use App\Models\VehicleType;
use App\Models\Wallet;
use App\Services\Settings\PlatformSettings;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionPurchaseTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);
        $this->travelTo('2026-11-19 10:00:00');
        $this->business = Business::factory()->withoutMembership()->create();
    }

    private function fundWallet(int $balance): Wallet
    {
        return Wallet::factory()->for($this->business->owner)->create(['balance_xof' => $balance]);
    }

    private function membershipOffer(array $attributes = []): CommercialOffer
    {
        return CommercialOffer::factory()->create($attributes);
    }

    private function serviceBundle(int $quantity, int $price = 2000): CommercialOffer
    {
        return CommercialOffer::factory()->create([
            'type' => CommercialOfferType::ServiceBundle,
            'name' => "Pack {$quantity} services",
            'quantity' => $quantity,
            'price_xof' => $price,
        ]);
    }

    public function test_purchases_require_an_account_and_a_business(): void
    {
        $this->postJson('/api/v1/my-business/subscriptions')->assertUnauthorized();

        $this->actingAs(User::factory()->create())
            ->postJson('/api/v1/my-business/subscriptions', ['offer_id' => $this->membershipOffer()->id])
            ->assertNotFound();
    }

    public function test_a_membership_is_debited_from_the_wallet_and_makes_the_business_visible(): void
    {
        $wallet = $this->fundWallet(8000);
        $offer = $this->membershipOffer(['price_xof' => 5000]);

        $this->actingAs($this->business->owner)
            ->postJson('/api/v1/my-business/subscriptions', ['offer_id' => $offer->id, 'auto_renew' => true])
            ->assertCreated()
            ->assertJsonPath('data.type', 'membership')
            ->assertJsonPath('data.is_current', true)
            ->assertJsonPath('data.auto_renew', true)
            ->assertJsonPath('data.ends_at', '2026-12-19T10:00:00+00:00')
            ->assertJsonPath('meta.entitlements.membership.is_active', true)
            ->assertJsonPath('meta.balance.amount', 3000);

        $this->assertSame(3000, $wallet->refresh()->balance_xof);
        $this->assertDatabaseHas('wallet_entries', [
            'wallet_id' => $wallet->id,
            'type' => 'purchase',
            'direction' => 'debit',
            'amount_xof' => 5000,
            'balance_after_xof' => 3000,
        ]);
        $this->assertTrue($this->business->refresh()->isVisible());
        $this->getJson("/api/v1/businesses/{$this->business->slug}")->assertOk();
    }

    public function test_a_purchase_is_refused_when_the_balance_is_too_low(): void
    {
        $wallet = $this->fundWallet(1000);
        $offer = $this->membershipOffer(['price_xof' => 5000]);

        $this->actingAs($this->business->owner)
            ->postJson('/api/v1/my-business/subscriptions', ['offer_id' => $offer->id])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'insufficient_balance')
            ->assertJsonPath('missing.amount', 4000);

        $this->assertSame(1000, $wallet->refresh()->balance_xof);
        $this->assertDatabaseCount('business_subscriptions', 0);
        $this->assertDatabaseCount('wallet_entries', 0);
        $this->assertFalse($this->business->refresh()->isVisible());
    }

    public function test_an_inactive_offer_is_refused(): void
    {
        $this->fundWallet(50000);
        $inactive = $this->membershipOffer(['is_active' => false]);

        $this->actingAs($this->business->owner)
            ->postJson('/api/v1/my-business/subscriptions', ['offer_id' => $inactive->id])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'offer_not_available');

        $this->assertDatabaseCount('business_subscriptions', 0);
    }

    public function test_a_staff_bundle_is_debited_and_opens_team_places(): void
    {
        $wallet = $this->fundWallet(10000);
        $offer = CommercialOffer::factory()->create([
            'type' => CommercialOfferType::StaffBundle,
            'name' => 'Équipe de 2',
            'quantity' => 2,
            'price_xof' => 4000,
        ]);

        $this->actingAs($this->business->owner)
            ->postJson('/api/v1/my-business/subscriptions', ['offer_id' => $offer->id])
            ->assertCreated()
            ->assertJsonPath('data.type', 'staff_bundle')
            ->assertJsonPath('meta.entitlements.team', ['quota' => 2, 'count' => 0]);

        $this->assertSame(6000, $wallet->refresh()->balance_xof);
        $this->assertSame(2, $this->business->refresh()->team_quota);
    }

    public function test_a_new_membership_extends_the_current_one(): void
    {
        $this->fundWallet(10000);
        $offer = $this->membershipOffer(['price_xof' => 5000]);

        $this->actingAs($this->business->owner)
            ->postJson('/api/v1/my-business/subscriptions', ['offer_id' => $offer->id])
            ->assertCreated();
        $this->actingAs($this->business->owner)
            ->postJson('/api/v1/my-business/subscriptions', ['offer_id' => $offer->id])
            ->assertCreated()
            ->assertJsonPath('data.starts_at', '2026-12-19T10:00:00+00:00')
            ->assertJsonPath('data.is_current', false);

        $this->assertSame('2027-01-19 10:00:00', $this->business->refresh()->membership_ends_at->toDateTimeString());
    }

    public function test_an_unlimited_membership_cannot_be_extended(): void
    {
        $this->fundWallet(10000);
        $this->actingAs($this->business->owner)->postJson('/api/v1/my-business/subscriptions', [
            'offer_id' => $this->membershipOffer([
                'is_unlimited' => true, 'duration_value' => null, 'duration_unit' => null, 'price_xof' => 1000,
            ])->id,
        ])->assertCreated();

        $this->actingAs($this->business->owner)
            ->postJson('/api/v1/my-business/subscriptions', ['offer_id' => $this->membershipOffer()->id])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Votre adhésion est déjà illimitée.');
    }

    public function test_service_bundles_publish_services_up_to_their_combined_quantity(): void
    {
        $this->fundWallet(10000);
        $serviceIds = Service::query()->orderBy('id')->limit(4)->pluck('id');
        $this->business->services()->createMany($serviceIds->map(fn (int $id) => ['service_id' => $id])->all());

        $this->actingAs($this->business->owner)
            ->postJson('/api/v1/my-business/subscriptions', ['offer_id' => $this->serviceBundle(2)->id])
            ->assertCreated()
            ->assertJsonPath('meta.entitlements.services.quota', 2)
            ->assertJsonPath('meta.entitlements.services.published_count', 2);

        $this->assertSame(
            $serviceIds->take(2)->all(),
            $this->business->publishedServices()->orderBy('service_id')->pluck('service_id')->all(),
        );

        $this->actingAs($this->business->owner)
            ->postJson('/api/v1/my-business/subscriptions', ['offer_id' => $this->serviceBundle(1)->id])
            ->assertCreated()
            ->assertJsonPath('meta.entitlements.services.quota', 3);
        $this->assertSame(3, $this->business->publishedServices()->count());
    }

    public function test_editing_services_keeps_them_within_the_quota(): void
    {
        BusinessSubscription::factory()->for($this->business)->serviceBundle(1)->create();
        $this->business->vehicleTypes()->attach(VehicleType::query()->value('id'));
        [$first, $second] = Service::query()->orderBy('id')->limit(2)->pluck('id')->all();

        $this->actingAs($this->business->owner)
            ->putJson('/api/v1/my-business/services', ['services' => [
                ['service_id' => $first, 'is_published' => true],
                ['service_id' => $second, 'is_published' => true],
            ]])
            ->assertOk()
            ->assertJsonPath('data.services.0.is_published', true)
            ->assertJsonPath('data.services.1.is_published', false)
            ->assertJsonPath('data.entitlements.services.quota', 1);
    }

    public function test_auto_renewal_can_be_switched_by_the_owner_only(): void
    {
        $subscription = BusinessSubscription::factory()->for($this->business)->create();
        $otherSubscription = BusinessSubscription::factory()->create();

        $this->actingAs($this->business->owner)
            ->patchJson("/api/v1/my-business/subscriptions/{$subscription->id}", ['auto_renew' => true])
            ->assertOk()
            ->assertJsonPath('data.auto_renew', true);
        $this->assertTrue($subscription->refresh()->auto_renew);

        $this->actingAs($this->business->owner)
            ->patchJson("/api/v1/my-business/subscriptions/{$otherSubscription->id}", ['auto_renew' => true])
            ->assertNotFound();
        $this->assertFalse($otherSubscription->refresh()->auto_renew);
    }

    public function test_the_owner_lists_active_and_past_subscriptions(): void
    {
        BusinessSubscription::factory()->for($this->business)->create(['name' => 'En cours']);
        BusinessSubscription::factory()->for($this->business)->create([
            'name' => 'Terminée',
            'status' => 'expired',
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subMonth(),
        ]);
        app(PlatformSettings::class)->update([PlatformSettings::REQUEST_ACCEPTANCE_PRICE_XOF => 500]);

        $this->actingAs($this->business->owner)
            ->getJson('/api/v1/my-business/subscriptions')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'En cours')
            ->assertJsonPath('data.1.name', 'Terminée')
            ->assertJsonPath('data.1.can_auto_renew', false)
            ->assertJsonPath('meta.acceptance_price.amount', 500);
    }
}
