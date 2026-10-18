<?php

namespace Tests\Feature\Business;

use App\Models\Business;
use App\Models\District;
use App\Models\Service;
use App\Models\Trade;
use App\Models\User;
use App\Models\VehicleType;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class MyBusinessTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);
        $this->user = User::factory()->create(['phone' => '+22890123456']);
    }

    private function service(string $slug): Service
    {
        return Service::firstWhere('slug', $slug);
    }

    private function vehicle(string $slug): VehicleType
    {
        return VehicleType::firstWhere('slug', $slug);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function expressSignup(array $overrides = []): array
    {
        $district = District::firstWhere('slug', 'adidogome');

        return [
            'trade_id' => Trade::firstWhere('slug', 'mecanique')->id,
            'name' => 'Garage de la Paix',
            'phone' => '90 12 34 56',
            'whatsapp_phone' => '90 12 34 56',
            'district_id' => $district->id,
            'latitude' => 6.2101,
            'longitude' => 1.1547,
            'vehicle_type_ids' => [$this->vehicle('moto')->id, $this->vehicle('voiture')->id],
            'service_ids' => [$this->service('vidange')->id, $this->service('freins')->id],
            ...$overrides,
        ];
    }

    private function createBusiness(array $overrides = []): TestResponse
    {
        return $this->actingAs($this->user)->postJson('/api/v1/my-business', $this->expressSignup($overrides));
    }

    public function test_the_routes_require_authentication(): void
    {
        $this->getJson('/api/v1/my-business')->assertUnauthorized();
        $this->postJson('/api/v1/my-business', [])->assertUnauthorized();
    }

    public function test_a_user_without_business_gets_null(): void
    {
        $this->actingAs($this->user)->getJson('/api/v1/my-business')
            ->assertOk()
            ->assertExactJson(['data' => null]);
    }

    public function test_express_signup_creates_a_published_business(): void
    {
        $response = $this->createBusiness()
            ->assertCreated()
            ->assertJsonPath('data.name', 'Garage de la Paix')
            ->assertJsonPath('data.slug', 'garage-de-la-paix-adidogome')
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.phone', '+22890123456')
            ->assertJsonPath('data.whatsapp_phone', '+22890123456')
            ->assertJsonPath('data.city_id', District::firstWhere('slug', 'adidogome')->city_id)
            ->assertJsonCount(2, 'data.services');

        $this->assertEqualsCanonicalizing(
            [$this->vehicle('moto')->id, $this->vehicle('voiture')->id],
            $response->json('data.vehicle_type_ids'),
        );
        $this->assertSame($this->user->id, Business::first()->owner_user_id);

        $this->actingAs($this->user)->getJson('/api/v1/my-business')
            ->assertOk()
            ->assertJsonPath('data.id', $response->json('data.id'));
    }

    public function test_whatsapp_is_optional_and_the_call_number_may_be_a_landline(): void
    {
        $this->createBusiness(['whatsapp_phone' => null, 'phone' => '22 21 00 00'])
            ->assertCreated()
            ->assertJsonPath('data.whatsapp_phone', null);
    }

    public function test_a_user_can_only_register_one_business(): void
    {
        $this->createBusiness()->assertCreated();

        $this->createBusiness(['name' => 'Second garage'])->assertStatus(409);
    }

    public function test_slugs_stay_unique(): void
    {
        $this->createBusiness()->assertCreated();
        $other = User::factory()->create();

        $this->actingAs($other)
            ->postJson('/api/v1/my-business', $this->expressSignup())
            ->assertCreated()
            ->assertJsonPath('data.slug', 'garage-de-la-paix-adidogome-2');
    }

    public function test_express_signup_validation(): void
    {
        $this->createBusiness([
            'name' => '',
            'whatsapp_phone' => '22 21 00 00',
            'latitude' => 0,
            'longitude' => 0,
            'service_ids' => [],
            'vehicle_type_ids' => [],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'whatsapp_phone', 'latitude', 'service_ids', 'vehicle_type_ids'])
            ->assertJsonPath('errors.latitude.0', 'La position doit se trouver au Togo. Reprenez la position sur place.')
            ->assertJsonPath('errors.vehicle_type_ids.0', 'Choisissez au moins un type de véhicule.')
            ->assertJsonPath('errors.name.0', 'Le champ nom du garage est obligatoire.');
    }

    public function test_services_must_belong_to_the_trade_and_be_active(): void
    {
        $plumbing = Trade::create(['slug' => 'plomberie', 'name' => 'Plomberie', 'is_active' => true]);
        $leak = $plumbing->services()->create(['slug' => 'fuite', 'name' => 'Fuite']);
        $this->service('soudure')->update(['is_active' => false]);

        $this->createBusiness(['service_ids' => [$leak->id, $this->service('soudure')->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['service_ids.0', 'service_ids.1']);
    }

    public function test_inactive_districts_are_refused(): void
    {
        $district = District::firstWhere('slug', 'adidogome');
        $district->update(['is_active' => false]);

        $this->createBusiness(['district_id' => $district->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('district_id');
    }

    public function test_the_owner_completes_the_profile(): void
    {
        $this->createBusiness();
        $bè = District::firstWhere('slug', 'be');

        $this->actingAs($this->user)->patchJson('/api/v1/my-business', [
            'description' => 'Spécialiste moto depuis 15 ans.',
            'landmark' => 'En face de la pharmacie du carrefour',
            'district_id' => $bè->id,
            'latitude' => 6.1427,
            'longitude' => 1.2485,
            'is_mobile' => true,
            'service_radius_km' => 5,
            'is_24_7' => true,
            'whatsapp_phone' => null,
        ])
            ->assertOk()
            ->assertJsonPath('data.description', 'Spécialiste moto depuis 15 ans.')
            ->assertJsonPath('data.district_id', $bè->id)
            ->assertJsonPath('data.latitude', 6.1427)
            ->assertJsonPath('data.is_mobile', true)
            ->assertJsonPath('data.service_radius_km', 5)
            ->assertJsonPath('data.is_24_7', true)
            ->assertJsonPath('data.whatsapp_phone', null)
            // Le nom et le slug n'ont pas été envoyés : ils ne changent pas.
            ->assertJsonPath('data.name', 'Garage de la Paix')
            ->assertJsonPath('data.slug', 'garage-de-la-paix-adidogome');

        $this->actingAs($this->user)->patchJson('/api/v1/my-business', ['is_mobile' => false])
            ->assertOk()
            ->assertJsonPath('data.service_radius_km', null);
    }

    public function test_latitude_and_longitude_go_together(): void
    {
        $this->createBusiness();

        $this->actingAs($this->user)->patchJson('/api/v1/my-business', ['latitude' => 6.14])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('longitude');
    }

    public function test_management_routes_need_a_business(): void
    {
        $this->actingAs($this->user)->patchJson('/api/v1/my-business', ['name' => 'X'])
            ->assertNotFound()
            ->assertJsonPath('message', "Vous n'avez pas encore inscrit de garage.");
    }

    public function test_services_and_prices_are_replaced(): void
    {
        $this->createBusiness();
        $moto = $this->vehicle('moto');

        $this->actingAs($this->user)->putJson('/api/v1/my-business/services', [
            'services' => [
                ['service_id' => $this->service('vidange')->id, 'price_min' => 5000, 'price_max' => 15000],
                ['service_id' => $this->service('vidange')->id, 'vehicle_type_id' => $moto->id, 'price_min' => 1500, 'price_max' => 2500],
                ['service_id' => $this->service('pneus')->id, 'description' => 'Vulcanisation sur place'],
            ],
        ])
            ->assertOk()
            ->assertJsonCount(3, 'data.services')
            ->assertJsonPath('data.services.1.vehicle_type_id', $moto->id)
            ->assertJsonPath('data.services.1.price_min', 1500)
            ->assertJsonPath('data.services.2.description', 'Vulcanisation sur place');
    }

    public function test_service_prices_are_validated(): void
    {
        $this->createBusiness();
        $truck = $this->vehicle('poids-lourd');
        $oilChange = $this->service('vidange')->id;

        $this->actingAs($this->user)->putJson('/api/v1/my-business/services', [
            'services' => [
                ['service_id' => $oilChange, 'price_min' => 10000, 'price_max' => 5000],
                ['service_id' => $this->service('freins')->id, 'vehicle_type_id' => $truck->id],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['services.1.vehicle_type_id']);

        $this->actingAs($this->user)->putJson('/api/v1/my-business/services', [
            'services' => [
                ['service_id' => $oilChange, 'price_min' => 10000, 'price_max' => 5000],
                ['service_id' => $oilChange],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['services.0.price_max', 'services.1.service_id']);
    }

    public function test_removing_a_vehicle_type_removes_its_prices(): void
    {
        $this->createBusiness();
        $moto = $this->vehicle('moto');
        $this->actingAs($this->user)->putJson('/api/v1/my-business/services', [
            'services' => [
                ['service_id' => $this->service('vidange')->id],
                ['service_id' => $this->service('vidange')->id, 'vehicle_type_id' => $moto->id, 'price_min' => 1500],
            ],
        ])->assertOk();

        $this->actingAs($this->user)->patchJson('/api/v1/my-business', [
            'vehicle_type_ids' => [$this->vehicle('voiture')->id],
        ])
            ->assertOk()
            ->assertJsonCount(1, 'data.services')
            ->assertJsonPath('data.services.0.vehicle_type_id', null);
    }

    public function test_opening_hours_are_replaced(): void
    {
        $this->createBusiness();
        $weekdays = array_map(fn (int $day) => ['day' => $day, 'opens_at' => '07:30', 'closes_at' => '18:30'], range(1, 6));

        $this->actingAs($this->user)->putJson('/api/v1/my-business/opening-hours', [
            'opening_hours' => [...$weekdays, ['day' => 7, 'opens_at' => '09:00', 'closes_at' => '13:00']],
        ])
            ->assertOk()
            ->assertJsonCount(7, 'data.opening_hours')
            ->assertJsonPath('data.opening_hours.0', ['day' => 1, 'opens_at' => '07:30', 'closes_at' => '18:30'])
            ->assertJsonPath('data.opening_hours.6.closes_at', '13:00');

        $this->actingAs($this->user)->putJson('/api/v1/my-business/opening-hours', ['opening_hours' => []])
            ->assertOk()
            ->assertJsonCount(0, 'data.opening_hours');
    }

    public function test_opening_hours_are_validated(): void
    {
        $this->createBusiness();

        $this->actingAs($this->user)->putJson('/api/v1/my-business/opening-hours', [
            'opening_hours' => [
                ['day' => 1, 'opens_at' => '18:00', 'closes_at' => '08:00'],
                ['day' => 2, 'opens_at' => '08:00', 'closes_at' => '13:00'],
                ['day' => 2, 'opens_at' => '12:00', 'closes_at' => '18:00'],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['opening_hours.0.closes_at', 'opening_hours.2.opens_at']);

        $this->actingAs($this->user)->putJson('/api/v1/my-business/opening-hours', [
            'opening_hours' => [['day' => 8, 'opens_at' => '8h', 'closes_at' => '18:00']],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['opening_hours.0.day', 'opening_hours.0.opens_at']);
    }
}
