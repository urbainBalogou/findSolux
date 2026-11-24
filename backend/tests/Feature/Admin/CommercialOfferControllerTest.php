<?php

namespace Tests\Feature\Admin;

use App\Enums\CommercialOfferType;
use App\Enums\DurationUnit;
use App\Models\Admin;
use App\Models\CommercialOffer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommercialOfferControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_manage_commercial_offers(): void
    {
        $this->get('/admin/commercial-offers')
            ->assertRedirect('/admin/connexion');
    }

    public function test_admin_can_create_a_limited_membership_offer(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin')->post('/admin/commercial-offers', [
            'type' => CommercialOfferType::Membership->value,
            'name' => 'Adhésion annuelle',
            'price_xof' => 50000,
            'duration_type' => 'limited',
            'duration_value' => 1,
            'duration_unit' => DurationUnit::Year->value,
            'position' => 2,
            'is_active' => true,
        ])->assertRedirect()->assertSessionHas('success', 'L’offre a été créée.');

        $this->assertDatabaseHas('commercial_offers', [
            'type' => 'membership',
            'name' => 'Adhésion annuelle',
            'quantity' => null,
            'price_xof' => 50000,
            'duration_value' => 1,
            'duration_unit' => 'year',
            'is_unlimited' => false,
            'is_active' => true,
            'position' => 2,
        ]);
    }

    public function test_admin_can_create_an_unlimited_service_bundle(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin')->post('/admin/commercial-offers', [
            'type' => CommercialOfferType::ServiceBundle->value,
            'name' => 'Services illimités',
            'quantity' => 25,
            'price_xof' => 30000,
            'duration_type' => 'unlimited',
            'position' => 1,
            'is_active' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('commercial_offers', [
            'type' => 'service_bundle',
            'quantity' => 25,
            'duration_value' => null,
            'duration_unit' => null,
            'is_unlimited' => true,
        ]);
    }

    public function test_offer_quantity_and_duration_contract_is_validated(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin')->post('/admin/commercial-offers', [
            'type' => CommercialOfferType::Membership->value,
            'name' => 'Formule invalide',
            'quantity' => 5,
            'price_xof' => 1000,
            'duration_type' => 'limited',
            'duration_value' => 2,
            'is_active' => true,
        ])
            ->assertSessionHasErrors(['quantity', 'duration_unit']);

        $this->assertDatabaseCount('commercial_offers', 0);
    }

    public function test_admin_can_update_an_offer_and_deactivate_it(): void
    {
        $admin = Admin::factory()->create();
        $offer = CommercialOffer::factory()->create();

        $this->actingAs($admin, 'admin')->put("/admin/commercial-offers/{$offer->id}", [
            'type' => CommercialOfferType::Membership->value,
            'name' => 'Adhésion permanente',
            'price_xof' => 75000,
            'duration_type' => 'unlimited',
            'position' => 3,
            'is_active' => false,
        ])->assertRedirect()->assertSessionHas('success', 'L’offre a été mise à jour.');

        $this->assertDatabaseHas('commercial_offers', [
            'id' => $offer->id,
            'name' => 'Adhésion permanente',
            'price_xof' => 75000,
            'duration_value' => null,
            'duration_unit' => null,
            'is_unlimited' => true,
            'is_active' => false,
        ]);
    }

    public function test_offer_index_renders_all_pricing_sections(): void
    {
        $admin = Admin::factory()->create();
        CommercialOffer::factory()->create(['name' => 'Adhésion mensuelle']);

        $this->actingAs($admin, 'admin')->get('/admin/commercial-offers')
            ->assertOk()
            ->assertSee('Adhésion mensuelle')
            ->assertSee('Offres de services')
            ->assertSee('Employés et répondeurs')
            ->assertSee('Acceptation des demandes');
    }
}
