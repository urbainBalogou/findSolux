<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Service;
use App\Models\Trade;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferenceDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);
    }

    public function test_it_returns_cities_trades_and_vehicle_types(): void
    {
        $response = $this->getJson('/api/v1/reference-data')->assertOk();

        $response->assertJsonPath('data.cities.0.slug', 'lome');
        $response->assertJsonPath('data.trades.0.slug', 'mecanique');
        $response->assertJsonPath('data.trades.0.services.0.slug', 'vidange');
        $response->assertJsonCount(4, 'data.vehicle_types');

        $districts = collect($response->json('data.cities.0.districts'));
        $this->assertContains('Adidogomé', $districts->pluck('name'));
        $this->assertIsFloat($districts->firstWhere('slug', 'adidogome')['latitude']);
    }

    public function test_inactive_entries_are_hidden(): void
    {
        City::create([
            'name' => 'Kara', 'slug' => 'kara', 'latitude' => 9.5511, 'longitude' => 1.1861, 'is_active' => false,
        ]);
        Trade::create(['slug' => 'plomberie', 'name' => 'Plomberie', 'is_active' => false]);
        Service::where('slug', 'soudure')->update(['is_active' => false]);

        $response = $this->getJson('/api/v1/reference-data')->assertOk();

        $this->assertSame(['lome'], array_column($response->json('data.cities'), 'slug'));
        $this->assertSame(['mecanique'], array_column($response->json('data.trades'), 'slug'));
        $this->assertNotContains('soudure', array_column($response->json('data.trades.0.services'), 'slug'));
    }

    public function test_unchanged_data_is_not_downloaded_again(): void
    {
        $etag = $this->getJson('/api/v1/reference-data')->assertOk()->headers->get('ETag');

        $this->assertNotEmpty($etag);

        $this->getJson('/api/v1/reference-data', ['If-None-Match' => $etag])->assertStatus(304);
    }

    public function test_seeder_can_run_twice_without_duplicates(): void
    {
        $districtCount = City::where('slug', 'lome')->first()->districts()->count();

        $this->seed(ReferenceDataSeeder::class);

        $this->assertSame(1, City::count());
        $this->assertSame($districtCount, City::where('slug', 'lome')->first()->districts()->count());
    }
}
