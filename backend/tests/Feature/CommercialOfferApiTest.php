<?php

namespace Tests\Feature;

use App\Enums\CommercialOfferType;
use App\Enums\DurationUnit;
use App\Models\CommercialOffer;
use App\Services\Settings\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommercialOfferApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_all_active_offers_in_business_order(): void
    {
        CommercialOffer::factory()->create([
            'type' => CommercialOfferType::StaffBundle,
            'name' => '3 répondeurs',
            'quantity' => 3,
            'position' => 1,
            'duration_value' => null,
            'duration_unit' => null,
            'is_unlimited' => true,
        ]);
        CommercialOffer::factory()->create([
            'type' => CommercialOfferType::Membership,
            'name' => 'Adhésion annuelle',
            'price_xof' => 50_000,
            'duration_value' => 1,
            'duration_unit' => DurationUnit::Year,
            'position' => 2,
        ]);
        CommercialOffer::factory()->create([
            'type' => CommercialOfferType::ServiceBundle,
            'name' => '10 services',
            'quantity' => 10,
            'price_xof' => 12_000,
            'position' => 1,
        ]);
        CommercialOffer::factory()->create([
            'name' => 'Offre masquée',
            'is_active' => false,
            'position' => 1,
        ]);

        $response = $this->getJson('/api/v1/commercial-offers')->assertOk();

        $response->assertJsonCount(3, 'data');
        $this->assertSame([
            'membership',
            'service_bundle',
            'staff_bundle',
        ], array_column($response->json('data'), 'type'));
        $response->assertJsonPath('data.0.price.amount', 50_000);
        $response->assertJsonPath('data.0.price.currency', 'XOF');
        $response->assertJsonPath('data.0.duration.type', 'limited');
        $response->assertJsonPath('data.0.duration.unit', 'year');
        $response->assertJsonPath('data.1.quantity', 10);
        $response->assertJsonPath('data.2.duration.type', 'unlimited');
        $response->assertJsonPath('data.2.duration.value', null);
        $response->assertJsonMissing(['name' => 'Offre masquée']);
    }

    public function test_the_catalogue_includes_the_single_request_acceptance_price(): void
    {
        app(PlatformSettings::class)->update([PlatformSettings::REQUEST_ACCEPTANCE_PRICE_XOF => 500]);

        $this->getJson('/api/v1/commercial-offers')
            ->assertOk()
            ->assertJsonPath('meta.acceptance_price', ['amount' => 500, 'currency' => 'XOF']);
    }

    public function test_unchanged_offers_are_not_downloaded_again(): void
    {
        CommercialOffer::factory()->create();

        $etag = $this->getJson('/api/v1/commercial-offers')->assertOk()->headers->get('ETag');

        $this->assertNotEmpty($etag);

        $this->getJson('/api/v1/commercial-offers', ['If-None-Match' => $etag])->assertStatus(304);
    }

    public function test_a_new_acceptance_price_is_downloaded_again(): void
    {
        $etag = $this->getJson('/api/v1/commercial-offers')->assertOk()->headers->get('ETag');

        app(PlatformSettings::class)->update([PlatformSettings::REQUEST_ACCEPTANCE_PRICE_XOF => 750]);

        $this->getJson('/api/v1/commercial-offers', ['If-None-Match' => $etag])
            ->assertOk()
            ->assertJsonPath('meta.acceptance_price.amount', 750);
    }
}
