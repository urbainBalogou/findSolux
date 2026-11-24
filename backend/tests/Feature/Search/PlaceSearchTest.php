<?php

namespace Tests\Feature\Search;

use App\Services\Places\GooglePlaces;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PlaceSearchTest extends TestCase
{
    use RefreshDatabase;

    private const GOOGLE = 'https://places.googleapis.com/v1/places:searchText';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);
        Http::preventStrayRequests();
    }

    private function withGoogleKey(): void
    {
        $this->app->instance(GooglePlaces::class, new GooglePlaces('cle-serveur-test'));
    }

    private function fakeGoogle(array $places): void
    {
        Http::fake([self::GOOGLE => Http::response(['places' => $places])]);
    }

    private function place(string $name, float $latitude, float $longitude): array
    {
        return [
            'id' => 'ChIJ'.md5($name),
            'displayName' => ['text' => $name, 'languageCode' => 'fr'],
            'formattedAddress' => "{$name}, Lomé, Togo",
            'location' => ['latitude' => $latitude, 'longitude' => $longitude],
        ];
    }

    public function test_districts_come_first_then_google_places(): void
    {
        $this->withGoogleKey();
        $this->fakeGoogle([$this->place('Marché d’Adidogomé', 6.2050, 1.1600)]);

        $this->getJson('/api/v1/places/search?q=adidogome')
            ->assertOk()
            // Sans accent, le quartier est trouvé quand même.
            ->assertJsonPath('data.0', [
                'type' => 'district',
                'name' => 'Adidogomé',
                'address' => 'Lomé',
                'latitude' => 6.21014,
                'longitude' => 1.15475,
            ])
            ->assertJsonPath('data.1.type', 'place')
            ->assertJsonPath('data.1.name', 'Marché d’Adidogomé')
            ->assertJsonPath('data.1.latitude', 6.205);

        Http::assertSent(fn (Request $request) => $request->hasHeader('X-Goog-Api-Key', 'cle-serveur-test')
            && $request->hasHeader('X-Goog-FieldMask')
            && $request['textQuery'] === 'adidogome'
            && $request['regionCode'] === 'TG'
            && $request['languageCode'] === 'fr');
    }

    public function test_places_outside_togo_are_dropped(): void
    {
        $this->withGoogleKey();
        $this->fakeGoogle([
            $this->place('Aflao Market', 6.1130, 1.1900),
            $this->place('Accra Mall', 5.6200, -0.1700),
        ]);

        $this->getJson('/api/v1/places/search?q=market')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Aflao Market');
    }

    public function test_identical_searches_call_google_once(): void
    {
        $this->withGoogleKey();
        $this->fakeGoogle([$this->place('Grand marché', 6.1300, 1.2200)]);

        $this->getJson('/api/v1/places/search?q=Grand marché')->assertOk();
        $this->getJson('/api/v1/places/search?q=grand  MARCHÉ')->assertOk()->assertJsonPath('data.0.name', 'Grand marché');

        Http::assertSentCount(1);
    }

    public function test_without_a_key_only_districts_are_searched(): void
    {
        $this->app->instance(GooglePlaces::class, new GooglePlaces(null));

        $this->getJson('/api/v1/places/search?q=tokoin')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'district');

        Http::assertNothingSent();
    }

    public function test_a_google_failure_still_returns_districts(): void
    {
        $this->withGoogleKey();
        Http::fake([self::GOOGLE => Http::response(['error' => ['message' => 'quota']], 429)]);

        $response = $this->getJson('/api/v1/places/search?q=baguida')->assertOk();

        $this->assertSame(['Baguida', 'Baguida-Dévégo'], array_column($response->json('data'), 'name'));
        $this->assertSame(['district'], array_unique(array_column($response->json('data'), 'type')));
    }

    public function test_the_query_is_validated(): void
    {
        $this->getJson('/api/v1/places/search?q=a')->assertUnprocessable()->assertJsonValidationErrors('q');
    }
}
