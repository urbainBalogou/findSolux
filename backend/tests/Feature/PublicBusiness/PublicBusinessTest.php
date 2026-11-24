<?php

namespace Tests\Feature\PublicBusiness;

use App\Enums\BusinessStatus;
use App\Enums\ContactSource;
use App\Models\Business;
use App\Models\Service;
use App\Models\User;
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
            'is_published' => true,
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
            ->assertJsonPath('data.contact', null)
            ->assertJsonPath('data.pending_contact_request', null)
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

    public function test_a_business_without_membership_is_hidden(): void
    {
        $this->business->forceFill(['membership_ends_at' => now()->subMinute()])->save();

        $this->getJson("/api/v1/businesses/{$this->business->slug}")->assertNotFound();
    }

    public function test_the_phone_number_is_shown_only_to_a_client_in_contact(): void
    {
        $client = User::factory()->create();
        $stranger = User::factory()->create();
        $this->business->contacts()->create(['client_user_id' => $client->id, 'source' => ContactSource::ContactRequest]);

        // Route publique : le jeton est lu par la garde « sanctum », sans middleware.
        $this->actingAs($client, 'sanctum')
            ->getJson("/api/v1/businesses/{$this->business->slug}")
            ->assertOk()
            ->assertJsonPath('data.contact', ['phone' => '+22890123456', 'whatsapp_phone' => '+22890123456']);

        $this->actingAs($stranger, 'sanctum')
            ->getJson("/api/v1/businesses/{$this->business->slug}")
            ->assertOk()
            ->assertJsonPath('data.contact', null);
    }

    public function test_services_not_covered_by_a_pack_are_hidden(): void
    {
        $this->business->services()->update(['is_published' => false]);

        $this->getJson("/api/v1/businesses/{$this->business->slug}")
            ->assertOk()
            ->assertJsonCount(0, 'data.services');
    }

    public function test_an_unknown_business_does_not_leak_internal_names(): void
    {
        $this->getJson('/api/v1/businesses/inconnu')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Élément introuvable.']);
    }
}
