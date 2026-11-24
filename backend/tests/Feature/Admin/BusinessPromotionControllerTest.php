<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Business;
use App\Models\BusinessPromotion;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessPromotionControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createBusiness(array $attributes = []): Business
    {
        $this->seed(ReferenceDataSeeder::class);

        return Business::factory()->create($attributes);
    }

    public function test_guest_cannot_manage_featured_businesses(): void
    {
        $this->get('/admin/business-promotions')
            ->assertRedirect('/admin/connexion');
    }

    public function test_admin_can_schedule_a_limited_featured_business(): void
    {
        $admin = Admin::factory()->create();
        $business = $this->createBusiness();

        $this->actingAs($admin, 'admin')->post('/admin/business-promotions', [
            'business_id' => $business->id,
            'position' => 2,
            'starts_at' => '2026-11-13T10:00',
            'duration_type' => 'limited',
            'duration_value' => 3,
            'duration_unit' => 'month',
        ])->assertRedirect()->assertSessionHas('success', 'Le garage a été mis en vedette.');

        $this->assertDatabaseHas('business_promotions', [
            'business_id' => $business->id,
            'position' => 2,
            'starts_at' => '2026-11-13 10:00:00',
            'ends_at' => '2027-02-13 10:00:00',
            'duration_value' => 3,
            'duration_unit' => 'month',
            'is_unlimited' => false,
        ]);
    }

    public function test_admin_can_schedule_an_unlimited_featured_business(): void
    {
        $admin = Admin::factory()->create();
        $business = $this->createBusiness();

        $this->actingAs($admin, 'admin')->post('/admin/business-promotions', [
            'business_id' => $business->id,
            'position' => 1,
            'starts_at' => '2026-11-13T10:00',
            'duration_type' => 'unlimited',
        ])->assertRedirect();

        $this->assertDatabaseHas('business_promotions', [
            'business_id' => $business->id,
            'ends_at' => null,
            'duration_value' => null,
            'duration_unit' => null,
            'is_unlimited' => true,
        ]);
    }

    public function test_a_business_can_only_have_one_featured_schedule(): void
    {
        $admin = Admin::factory()->create();
        $business = $this->createBusiness();
        BusinessPromotion::factory()->for($business)->create();

        $this->actingAs($admin, 'admin')->post('/admin/business-promotions', [
            'business_id' => $business->id,
            'position' => 2,
            'starts_at' => '2026-11-13T10:00',
            'duration_type' => 'unlimited',
        ])->assertSessionHasErrors('business_id');

        $this->assertDatabaseCount('business_promotions', 1);
    }

    public function test_admin_can_update_and_remove_a_featured_schedule(): void
    {
        $admin = Admin::factory()->create();
        $business = $this->createBusiness();
        $promotion = BusinessPromotion::factory()->for($business)->create();

        $this->actingAs($admin, 'admin')->put("/admin/business-promotions/{$promotion->id}", [
            'business_id' => $business->id,
            'position' => 4,
            'starts_at' => '2026-12-01T08:30',
            'duration_type' => 'limited',
            'duration_value' => 15,
            'duration_unit' => 'day',
        ])->assertRedirect();

        $this->assertDatabaseHas('business_promotions', [
            'id' => $promotion->id,
            'position' => 4,
            'ends_at' => '2026-12-16 08:30:00',
        ]);

        $this->actingAs($admin, 'admin')
            ->delete("/admin/business-promotions/{$promotion->id}")
            ->assertRedirect()
            ->assertSessionHas('success', 'Le garage a été retiré de la mise en vedette.');

        $this->assertDatabaseCount('business_promotions', 0);
    }

    public function test_featured_business_index_escapes_business_names(): void
    {
        $admin = Admin::factory()->create();
        $business = $this->createBusiness(['name' => '<script>alert("xss")</script>']);
        BusinessPromotion::factory()->for($business)->create();

        $this->actingAs($admin, 'admin')->get('/admin/business-promotions')
            ->assertOk()
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>alert("xss")</script>', false);
    }

    public function test_featured_schedule_requires_a_complete_limited_duration(): void
    {
        $admin = Admin::factory()->create();
        $business = $this->createBusiness();

        $this->actingAs($admin, 'admin')->post('/admin/business-promotions', [
            'business_id' => $business->id,
            'position' => 1,
            'starts_at' => '2026-11-13T10:00',
            'duration_type' => 'limited',
            'duration_value' => 1,
        ])->assertSessionHasErrors('duration_unit');

        $this->assertDatabaseCount('business_promotions', 0);
    }
}
