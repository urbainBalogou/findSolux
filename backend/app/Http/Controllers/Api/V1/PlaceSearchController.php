<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Services\Places\GooglePlaces;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

/**
 * Recherche d'un lieu depuis la carte : d'abord les quartiers connus (gratuit,
 * sans accent requis : « adidogome » trouve Adidogomé), puis les lieux Google.
 */
class PlaceSearchController extends Controller
{
    public function __invoke(Request $request, GooglePlaces $places): JsonResponse
    {
        $query = Str::squish((string) $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ])['q']);

        $districts = District::active()
            ->with('city')
            ->where('name', 'like', '%'.addcslashes($query, '%_\\').'%')
            ->orderBy('name')
            ->limit(5)
            ->get()
            ->map(fn (District $district) => [
                'type' => 'district',
                'name' => $district->name,
                'address' => $district->city->name,
                'latitude' => $district->latitude,
                'longitude' => $district->longitude,
            ]);

        $found = [];
        if ($places->isConfigured()) {
            try {
                $found = array_map(fn (array $place) => ['type' => 'place', ...$place], $places->search($query));
            } catch (Throwable $error) {
                // Google indisponible : les quartiers suffisent à continuer.
                report($error);
            }
        }

        return response()->json(['data' => [...$districts->all(), ...$found]]);
    }
}
