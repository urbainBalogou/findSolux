<?php

namespace Tests\Feature\Admin;

use App\Enums\CommercialOfferType;
use App\Enums\SubscriptionSource;
use App\Models\Admin;
use App\Models\Business;
use App\Models\CommercialOffer;
use App\Services\Settings\PlatformSettings;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_reach_settings_or_businesses(): void
    {
        $this->get('/admin/parametres')->assertRedirect('/admin/connexion');
        $this->get('/admin/garages')->assertRedirect('/admin/connexion');
        $this->put('/admin/tarifs/acceptation', ['request_acceptance_price_xof' => 0])->assertRedirect('/admin/connexion');
    }

    public function test_admin_sets_the_single_request_acceptance_price(): void
    {
        $admin = Admin::factory()->create();
        app(PlatformSettings::class)->update([PlatformSettings::REQUEST_ACCEPTANCE_PRICE_XOF => 500]);

        $this->actingAs($admin, 'admin')
            ->get('/admin/commercial-offers')
            ->assertOk()
            ->assertSee('Prix d’une demande acceptée (FCFA)')
            ->assertSee('name="request_acceptance_price_xof"', false)
            ->assertSee('value="500"', false)
            ->assertDontSee('Ajouter une offre acceptation des demandes');

        $this->actingAs($admin, 'admin')
            ->put('/admin/tarifs/acceptation', ['request_acceptance_price_xof' => 750])
            ->assertRedirect()
            ->assertSessionHas('success', 'Le prix d’une demande acceptée a été enregistré.');

        $this->assertSame(750, app(PlatformSettings::class)->requestAcceptancePriceXof());
    }

    public function test_the_request_acceptance_price_cannot_be_negative(): void
    {
        app(PlatformSettings::class)->update([PlatformSettings::REQUEST_ACCEPTANCE_PRICE_XOF => 500]);

        $this->actingAs(Admin::factory()->create(), 'admin')
            ->put('/admin/tarifs/acceptation', ['request_acceptance_price_xof' => -1])
            ->assertSessionHasErrors(['request_acceptance_price_xof' => 'La valeur de prix d’une demande acceptée doit être supérieure ou égale à 0.']);

        $this->assertSame(500, app(PlatformSettings::class)->requestAcceptancePriceXof());
    }

    public function test_admin_updates_the_request_delays(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin')
            ->get('/admin/parametres')
            ->assertOk()
            ->assertSee('value="10"', false);

        $this->actingAs($admin, 'admin')
            ->put('/admin/parametres', ['distress_signal_wait_minutes' => 5, 'contact_request_wait_hours' => 48])
            ->assertRedirect()
            ->assertSessionHas('success');

        $settings = app(PlatformSettings::class);
        $this->assertSame(5, $settings->distressSignalWaitMinutes());
        $this->assertSame(48, $settings->contactRequestWaitHours());
    }

    public function test_delays_are_validated(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin')
            ->put('/admin/parametres', ['distress_signal_wait_minutes' => 0, 'contact_request_wait_hours' => 500])
            ->assertSessionHasErrors(['distress_signal_wait_minutes', 'contact_request_wait_hours']);
    }

    public function test_admin_grants_a_free_membership_that_makes_the_business_visible(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $admin = Admin::factory()->create();
        $business = Business::factory()->withoutMembership()->create(['name' => 'Garage du Port']);
        $offer = CommercialOffer::factory()->create(['name' => 'Adhésion lancement', 'price_xof' => 5000]);

        $this->actingAs($admin, 'admin')
            ->get('/admin/garages?q=Port')
            ->assertOk()
            ->assertSee('Garage du Port')
            ->assertSee('Sans adhésion · invisible');

        $this->actingAs($admin, 'admin')
            ->post("/admin/garages/{$business->id}/offres", ['offer_id' => $offer->id])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('business_subscriptions', [
            'business_id' => $business->id,
            'price_xof' => 0,
            'source' => SubscriptionSource::Admin->value,
            'granted_by_admin_id' => $admin->id,
        ]);
        $this->assertTrue($business->refresh()->isVisible());
        $this->assertDatabaseCount('wallet_entries', 0);
    }

    public function test_admin_grants_a_staff_bundle_that_opens_team_places(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $business = Business::factory()->create();
        $offer = CommercialOffer::factory()->create([
            'type' => CommercialOfferType::StaffBundle,
            'quantity' => 3,
        ]);

        $this->actingAs(Admin::factory()->create(), 'admin')
            ->post("/admin/garages/{$business->id}/offres", ['offer_id' => $offer->id])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(3, $business->refresh()->team_quota);
    }
}
