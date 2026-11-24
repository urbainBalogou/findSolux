<?php

namespace Tests\Feature\PublicBusiness;

use App\Enums\BusinessStatus;
use App\Models\Business;
use App\Models\District;
use App\Models\Service;
use App\Models\VehicleType;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GaragePageTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);
        $district = District::firstWhere('slug', 'adidogome');
        $this->business = Business::factory()->create([
            'name' => 'Garage de la Paix',
            'phone' => '+22890123456',
            'whatsapp_phone' => '+22890123456',
            'district_id' => $district->id,
            'city_id' => $district->city_id,
            'landmark' => 'En face de la pharmacie',
            'latitude' => 6.2101,
            'longitude' => 1.1547,
        ]);
        $this->business->vehicleTypes()->attach(VehicleType::firstWhere('slug', 'moto'));
        $this->business->services()->createMany([
            ['service_id' => Service::firstWhere('slug', 'vidange')->id, 'price_min' => 5000, 'price_max' => 15000, 'is_published' => true],
            ['service_id' => Service::firstWhere('slug', 'freins')->id, 'vehicle_type_id' => VehicleType::firstWhere('slug', 'moto')->id, 'is_published' => true],
        ]);
    }

    public function test_the_page_shows_the_business_without_its_phone_number(): void
    {
        config(['findsolux.app_download_url' => 'https://play.google.com/store/apps/details?id=com.mecadispo.app']);

        $this->get("/garages/{$this->business->slug}")
            ->assertOk()
            ->assertSee('<title>Garage de la Paix — garage à Adidogomé, Lomé | MecaDispo</title>', false)
            ->assertSee('<meta property="og:title" content="Garage de la Paix — Adidogomé, Lomé">', false)
            ->assertDontSee('tel:', false)
            ->assertDontSee('wa.me', false)
            ->assertDontSee('90 12 34 56')
            ->assertSee('Contacter ce garage dans l’application')
            ->assertSee('https://www.google.com/maps/dir/?api=1&amp;destination=6.2101,1.1547', false)
            ->assertSee('En face de la pharmacie')
            ->assertSee("5\u{202F}000 – 15\u{202F}000 FCFA")
            ->assertSee('Freins · Moto')
            ->assertSee('Prix sur demande')
            ->assertSee('Horaires non renseignés');
    }

    public function test_opening_hours_and_status(): void
    {
        $this->travelTo(now('Africa/Lome')->next('Monday')->setTime(10, 0));
        $this->business->openingHours()->create(['day_of_week' => 1, 'opens_at' => '08:00', 'closes_at' => '18:00']);

        $this->get("/garages/{$this->business->slug}")
            ->assertOk()
            ->assertSee('Ouvert maintenant')
            ->assertSeeInOrder(['Lundi', '08:00–18:00', 'Mardi', 'Fermé']);
    }

    public function test_services_not_covered_by_a_pack_are_not_shown(): void
    {
        $this->business->services()->where('service_id', Service::firstWhere('slug', 'freins')->id)->update(['is_published' => false]);

        $this->get("/garages/{$this->business->slug}")
            ->assertOk()
            ->assertSee('Vidange')
            ->assertDontSee('Freins · Moto');
    }

    public function test_a_business_without_membership_has_no_page(): void
    {
        $this->business->forceFill(['membership_ends_at' => now()->subMinute()])->save();

        $this->get("/garages/{$this->business->slug}")->assertNotFound();
    }

    public function test_user_content_is_escaped(): void
    {
        $this->business->update(['description' => '<script>alert(1)</script>']);

        $this->get("/garages/{$this->business->slug}")
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_a_suspended_business_has_no_page(): void
    {
        $this->business->forceFill(['status' => BusinessStatus::Suspended])->save();

        $this->get("/garages/{$this->business->slug}")->assertNotFound();
    }
}
