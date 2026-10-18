<?php

namespace Tests\Feature\Search;

use App\Enums\BusinessStatus;
use App\Models\Business;
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

    public function test_the_search_is_public_and_returns_card_fields(): void
    {
        $business = $this->businessAt(1, ['landmark' => 'Près du marché', 'is_24_7' => true]);
        $business->vehicleTypes()->attach(VehicleType::firstWhere('slug', 'moto'));
        $business->services()->createMany([
            ['service_id' => Service::firstWhere('slug', 'vidange')->id],
            ['service_id' => Service::firstWhere('slug', 'vidange')->id, 'vehicle_type_id' => VehicleType::firstWhere('slug', 'moto')->id],
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

    public function test_results_can_be_filtered_by_vehicle_and_service(): void
    {
        $moto = VehicleType::firstWhere('slug', 'moto');
        $brakes = Service::firstWhere('slug', 'freins');
        $motoGarage = $this->businessAt(1);
        $motoGarage->vehicleTypes()->attach($moto);
        $motoGarage->services()->create(['service_id' => $brakes->id]);
        $carGarage = $this->businessAt(2);
        $carGarage->vehicleTypes()->attach(VehicleType::firstWhere('slug', 'voiture'));

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
