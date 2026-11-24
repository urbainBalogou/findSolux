<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CityResource;
use App\Http\Resources\TradeResource;
use App\Http\Resources\VehicleTypeResource;
use App\Models\City;
use App\Models\Trade;
use App\Models\VehicleType;
use Illuminate\Http\JsonResponse;

class ReferenceDataController extends Controller
{
    /**
     * Tous les référentiels actifs en une seule réponse, que l'application
     * garde en cache (ETag : une réponse 304 vide si rien n'a changé).
     */
    public function __invoke(): JsonResponse
    {
        $cities = City::active()
            ->with(['districts' => fn ($query) => $query->active()->orderBy('name')])
            ->orderBy('name')
            ->get();

        $trades = Trade::active()
            ->with(['services' => fn ($query) => $query->active()->orderBy('position')])
            ->orderBy('position')
            ->get();

        $vehicleTypes = VehicleType::active()->orderBy('position')->get();

        return response()->json([
            'data' => [
                'cities' => CityResource::collection($cities),
                'trades' => TradeResource::collection($trades),
                'vehicle_types' => VehicleTypeResource::collection($vehicleTypes),
            ],
        ]);
    }
}
