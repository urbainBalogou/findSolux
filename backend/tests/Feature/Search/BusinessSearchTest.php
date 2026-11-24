<?php

namespace Tests\Feature\Search;

use App\Enums\BusinessStatus;
use App\Models\Business;
use App\Models\BusinessPromotion;
use App\Models\Service;
use App\Models\VehicleType;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class BusinessSearchTest extends TestCase
{
    use RefreshDatabase;

    /** Point de recherche : Tokoin, Lomé. */
    private const LAT = 6.1500;

    private const LNG = 1.2200;

    /** Environ 1 km de latitude, en degrés. */
    private const KM = 1 / 111.2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);
    }

    /**
     * Garage situé à $km kilomètres au nord du point de recherche.
     */
    private function businessAt(float $km, array $attributes = []): Business
    {
        return Business::factory()->create([
            'latitude' => self::LAT + $km * self::KM,
            'longitude' => self::LNG,
            ...$attributes,
        ]);
    }

    private function search(array $parameters = []): TestResponse
    {
        return $this->getJson('/api/v1/businesses/search?'.http_build_query([
            'latitude' => self::LAT,
            'longitude' => self::LNG,
            ...$parameters,
        ]));
    }

    public function test_results_are_sorted_from_nearest_to_farthest(): void
    {
        $far = $this->businessAt(6);
        $near = $this->businessAt(0.3);
        $middle = $this->businessAt(2);

        $response = $this->search()->assertOk()->assertJsonPath('meta.radius_km', 10);

        $this->assertSame([$near->id, $middle->id, $far->id], array_column($response->json('data'), 'id'));
        $this->assertEqualsWithDelta(300, $response->json('data.0.distance_m'), 5);
        $this->assertEqualsWithDelta(6000, $response->json('data.2.distance_m'), 30);
    }

    public function test_featured_businesses_come_first_within_their_distance_band(): void
    {
        $this->travelTo('2026-11-13 12:00:00');
        // Tranches (R2) : 0–1 km, 1–3 km, 3–7 km, au-delà.
        $expired = $this->businessAt(0.1);
        $near = $this->businessAt(0.3);
        $featuredSecond = $this->businessAt(6);
        $featuredFirst = $this->businessAt(6.5);
        $closerInSameBand = $this->businessAt(4);

        BusinessPromotion::factory()->for($featuredSecond)->create([
            'position' => 2,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);
        BusinessPromotion::factory()->for($featuredFirst)->create([
            'position' => 1,
            'starts_at' => now()->subDay(),
            'ends_at' => null,
            'duration_value' => null,
            'duration_unit' => null,
            'is_unlimited' => true,
        ]);
        BusinessPromotion::factory()->for($expired)->create([
            'position' => 0,
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
        ]);

        $response = $this->search()->assertOk();

        // Un garage en vedette ne passe pas devant un garage plus proche d'une autre tranche.
        $this->assertSame(
            [$expired->id, $near->id, $featuredFirst->id, $featuredSecond->id, $closerInSameBand->id],
            array_column($response->json('data'), 'id'),
        );
        $response
            ->assertJsonPath('data.2.is_featured', true)
            ->assertJsonPath('data.2.featured_position', 1)
            ->assertJsonPath('data.0.is_featured', false)
            ->assertJsonPath('data.0.featured_position', null);
    }

    public function test_the_search_is_public_and_returns_card_fields(): void
    {
        $business = $this->businessAt(1, ['landmark' => 'Près du marché', 'is_24_7' => true]);
        $business->vehicleTypes()->attach(VehicleType::firstWhere('slug', 'moto'));
        $business->services()->createMany([
            ['service_id' => Service::firstWhere('slug', 'vidange')->id, 'is_published' => true],
            ['service_id' => Service::firstWhere('slug', 'vidange')->id, 'vehicle_type_id' => VehicleType::firstWhere('slug', 'moto')->id, 'is_published' => true],
            // Hors du quota des packs : invisible.
            ['service_id' => Service::firstWhere('slug', 'freins')->id],
        ]);

        $this->search()
            ->assertOk()
            ->assertJsonPath('data.0.name', $business->name)
            ->assertJsonPath('data.0.landmark', 'Près du marché')
            ->assertJsonPath('data.0.is_24_7', true)
            ->assertJsonPath('data.0.is_open_now', true)
            ->assertJsonPath('data.0.vehicle_type_ids', [VehicleType::firstWhere('slug', 'moto')->id])
            ->assertJsonPath('data.0.service_ids', [Service::firstWhere('slug', 'vidange')->id])
            ->assertJsonPath('data.0.photo_thumbnail_url', null);
    }

    public function test_the_radius_limits_the_results(): void
    {
        $this->businessAt(1);
        $this->businessAt(12);

        $this->search()->assertJsonCount(1, 'data');
        $this->search(['radius_km' => 15])->assertJsonCount(2, 'data');
    }

    public function test_a_mechanic_who_travels_appears_within_his_service_area(): void
    {
        $covers = $this->businessAt(12, ['is_mobile' => true, 'service_radius_km' => 15]);
        $this->businessAt(12, ['is_mobile' => true, 'service_radius_km' => 5]);

        $response = $this->search(['radius_km' => 5])->assertOk();

        $this->assertSame([$covers->id], array_column($response->json('data'), 'id'));
    }

    public function test_suspended_businesses_are_hidden(): void
    {
        $this->businessAt(1, ['status' => BusinessStatus::Suspended]);

        $this->search()->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_businesses_without_active_membership_are_hidden(): void
    {
        $this->businessAt(1, ['membership_ends_at' => now()->subMinute()]);
        $this->businessAt(1, ['membership_ends_at' => null]);
        $unlimited = $this->businessAt(2, ['membership_ends_at' => null, 'has_unlimited_membership' => true]);

        $response = $this->search()->assertOk();

        $this->assertSame([$unlimited->id], array_column($response->json('data'), 'id'));
        $this->assertArrayNotHasKey('phone', $response->json('data.0'));
    }

    public function test_results_can_be_filtered_by_vehicle_and_service(): void
    {
        $moto = VehicleType::firstWhere('slug', 'moto');
        $brakes = Service::firstWhere('slug', 'freins');
        $motoGarage = $this->businessAt(1);
        $motoGarage->vehicleTypes()->attach($moto);
        $motoGarage->services()->create(['service_id' => $brakes->id, 'is_published' => true]);
        $carGarage = $this->businessAt(2);
        $carGarage->vehicleTypes()->attach(VehicleType::firstWhere('slug', 'voiture'));
        // Service enregistré mais non couvert par un pack : ne compte pas pour le filtre.
        $carGarage->services()->create(['service_id' => $brakes->id]);

        $this->search(['vehicle_type_id' => $moto->id])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $motoGarage->id);

        $this->search(['service_id' => $brakes->id])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $motoGarage->id);
    }

    public function test_open_now_uses_lome_opening_hours(): void
    {
        // Lundi 10 h à Lomé.
        $this->travelTo(now('Africa/Lome')->next('Monday')->setTime(10, 0));

        $open = $this->businessAt(1);
        $open->openingHours()->create(['day_of_week' => 1, 'opens_at' => '08:00', 'closes_at' => '18:00']);
        $closed = $this->businessAt(2);
        $closed->openingHours()->create(['day_of_week' => 1, 'opens_at' => '14:00', 'closes_at' => '18:00']);
        $alwaysOpen = $this->businessAt(3, ['is_24_7' => true]);
        $this->businessAt(4);

        $all = $this->search()->assertOk();
        $this->assertSame([true, false, true, false], array_column($all->json('data'), 'is_open_now'));
        $this->assertSame([true, true, false, false], array_column($all->json('data'), 'has_opening_hours'));

        $openNow = $this->search(['open_now' => 1])->assertOk();
        $this->assertSame([$open->id, $alwaysOpen->id], array_column($openNow->json('data'), 'id'));
    }

    public function test_the_number_of_results_is_capped(): void
    {
        foreach (range(1, 4) as $km) {
            $this->businessAt($km);
        }

        $this->search(['limit' => 2])->assertJsonCount(2, 'data');
    }

    public function test_search_parameters_are_validated(): void
    {
        $this->getJson('/api/v1/businesses/search')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude', 'longitude']);

        // Paris : hors du Togo.
        $this->getJson('/api/v1/businesses/search?latitude=48.85&longitude=2.35')
            ->assertUnprocessable()
            ->assertJsonPath('errors.latitude.0', 'La recherche est disponible au Togo uniquement.');

        $this->search(['radius_km' => 100])->assertUnprocessable()->assertJsonValidationErrors('radius_km');
    }
}
