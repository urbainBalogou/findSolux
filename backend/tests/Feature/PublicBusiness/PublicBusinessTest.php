<?php

namespace Tests\Feature\PublicBusiness;

use App\Enums\BusinessStatus;
use App\Models\Business;
use App\Models\Service;
use App\Models\VehicleType;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicBusinessTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);
        $this->business = Business::factory()->create([
            'name' => 'Garage de la Paix',
            'phone' => '+22890123456',
            'whatsapp_phone' => '+22890123456',
            'description' => 'Spécialiste moto.',
        ]);
        $this->business->vehicleTypes()->attach(VehicleType::firstWhere('slug', 'moto'));
        $this->business->services()->create([
            'service_id' => Service::firstWhere('slug', 'vidange')->id,
            'price_min' => 5000,
            'price_max' => 15000,
        ]);
        $this->business->openingHours()->create(['day_of_week' => 1, 'opens_at' => '08:00', 'closes_at' => '18:00']);
    }

    public function test_the_public_profile_is_available_without_an_account(): void
    {
        // Lundi 10 h à Lomé : ouvert.
        $this->travelTo(now('Africa/Lome')->next('Monday')->setTime(10, 0));

        $this->getJson("/api/v1/businesses/{$this->business->slug}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Garage de la Paix')
            ->assertJsonPath('data.description', 'Spécialiste moto.')
            ->assertJsonPath('data.whatsapp_phone', '+22890123456')
            ->assertJsonPath('data.is_open_now', true)
            ->assertJsonPath('data.has_opening_hours', true)
            ->assertJsonPath('data.services.0.price_max', 15000)
            ->assertJsonPath('data.opening_hours.0', ['day' => 1, 'opens_at' => '08:00', 'closes_at' => '18:00'])
            ->assertJsonPath('data.share_url', route('garages.show', $this->business->slug));
    }

    public function test_the_profile_is_closed_outside_opening_hours(): void
    {
        $this->travelTo(now('Africa/Lome')->next('Monday')->setTime(19, 0));

        $this->getJson("/api/v1/businesses/{$this->business->slug}")->assertJsonPath('data.is_open_now', false);
    }

    public function test_a_suspended_business_is_hidden(): void
    {
        $this->business->forceFill(['status' => BusinessStatus::Suspended])->save();

        $this->getJson("/api/v1/businesses/{$this->business->slug}")
            ->assertNotFound()
            ->assertJsonPath('message', 'Ce garage n’est plus visible.');
    }

    public function test_an_unknown_business_does_not_leak_internal_names(): void
    {
        $this->getJson('/api/v1/businesses/inconnu')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Élément introuvable.']);
    }
}
