<?php

namespace App\Services\Places;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Recherche de lieux (API Places de Google, « Text Search »), appelée par le
 * serveur : la clé ne quitte jamais le serveur, les réponses sont mises en
 * cache (chaque appel est facturé par Google au-delà du quota gratuit).
 */
class GooglePlaces
{
    private const ENDPOINT = 'https://places.googleapis.com/v1/places:searchText';

    private const FIELDS = 'places.id,places.displayName,places.formattedAddress,places.location';

    public function __construct(private readonly ?string $apiKey) {}

    public function isConfigured(): bool
    {
        return filled($this->apiKey);
    }

    /**
     * Lieux du Togo correspondant à la saisie, du plus pertinent au moins pertinent.
     *
     * @return list<array{name: string, address: ?string, latitude: float, longitude: float}>
     *
     * @throws RequestException si Google répond en erreur
     */
    public function search(string $query): array
    {
        $cacheKey = 'places:'.sha1(Str::lower(Str::squish($query)));

        return Cache::remember($cacheKey, now()->addDay(), fn () => $this->fetch($query));
    }

    /**
     * @return list<array{name: string, address: ?string, latitude: float, longitude: float}>
     */
    private function fetch(string $query): array
    {
        [$minLat, $maxLat] = config('findsolux.country_bounds.latitude');
        [$minLng, $maxLng] = config('findsolux.country_bounds.longitude');

        $response = Http::withHeaders([
            'X-Goog-Api-Key' => $this->apiKey,
            'X-Goog-FieldMask' => self::FIELDS,
        ])
            ->acceptJson()
            ->timeout(5)
            ->post(self::ENDPOINT, [
                'textQuery' => $query,
                'languageCode' => 'fr',
                'regionCode' => 'TG',
                'pageSize' => 5,
                // Simple orientation : Google ne restreint pas les recherches
                // d'adresses ; les lieux hors du Togo sont écartés ci-dessous.
                'locationBias' => ['rectangle' => [
                    'low' => ['latitude' => $minLat, 'longitude' => $minLng],
                    'high' => ['latitude' => $maxLat, 'longitude' => $maxLng],
                ]],
            ])
            ->throw();

        return collect($response->json('places', []))
            ->filter(function (array $place) use ($minLat, $maxLat, $minLng, $maxLng) {
                $latitude = data_get($place, 'location.latitude');
                $longitude = data_get($place, 'location.longitude');

                return is_numeric($latitude) && is_numeric($longitude)
                    && $latitude >= $minLat && $latitude <= $maxLat
                    && $longitude >= $minLng && $longitude <= $maxLng;
            })
            ->map(fn (array $place) => [
                'name' => (string) data_get($place, 'displayName.text', ''),
                'address' => $place['formattedAddress'] ?? null,
                'latitude' => (float) data_get($place, 'location.latitude'),
                'longitude' => (float) data_get($place, 'location.longitude'),
            ])
            ->values()
            ->all();
    }
}
