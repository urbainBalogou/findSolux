<?php

namespace Tests\Feature\Offers;

use App\Services\Settings\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Passage des offres « Acceptation des demandes » au prix unique : le prix en
 * vigueur doit être repris, pas un autre.
 */
class RequestAcceptancePriceMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function insertOffer(string $type, int $price, bool $isActive, int $position): void
    {
        DB::table('commercial_offers')->insert([
            'type' => $type,
            'name' => "Offre {$price}",
            'price_xof' => $price,
            'is_unlimited' => true,
            'is_active' => $isActive,
            'position' => $position,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function runMigration(): void
    {
        $migration = require database_path('migrations/2026_11_24_100000_move_request_acceptance_price_to_settings.php');
        $migration->up();
    }

    public function test_the_price_in_force_becomes_the_single_price_and_acceptance_offers_are_removed(): void
    {
        $this->insertOffer('request_acceptance', 100, isActive: false, position: 0);
        $this->insertOffer('request_acceptance', 700, isActive: true, position: 2);
        $this->insertOffer('request_acceptance', 500, isActive: true, position: 1);
        $this->insertOffer('membership', 5000, isActive: true, position: 1);

        $this->runMigration();

        $this->assertSame(500, app(PlatformSettings::class)->requestAcceptancePriceXof());
        $this->assertDatabaseMissing('commercial_offers', ['type' => 'request_acceptance']);
        $this->assertDatabaseHas('commercial_offers', ['type' => 'membership', 'price_xof' => 5000]);
    }

    public function test_a_price_already_set_is_kept(): void
    {
        app(PlatformSettings::class)->update([PlatformSettings::REQUEST_ACCEPTANCE_PRICE_XOF => 300]);
        $this->insertOffer('request_acceptance', 500, isActive: true, position: 1);

        $this->runMigration();

        $this->assertSame(300, app(PlatformSettings::class)->requestAcceptancePriceXof());
    }
}
