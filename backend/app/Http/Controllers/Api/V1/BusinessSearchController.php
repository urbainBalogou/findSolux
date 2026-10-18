<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchBusinessesRequest;
use App\Http\Resources\BusinessSearchResultResource;
use App\Services\Search\BusinessSearch;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Garages proches d'un point (position du client ou lieu recherché), du plus
 * proche au plus éloigné. Accessible sans compte (C1).
 */
class BusinessSearchController extends Controller
{
    public function __invoke(SearchBusinessesRequest $request, BusinessSearch $search): AnonymousResourceCollection
    {
        $businesses = $search->around(
            (float) $request->validated('latitude'),
            (float) $request->validated('longitude'),
            $request->radiusKm(),
            $request->filters(),
            $request->limit(),
        );

        return BusinessSearchResultResource::collection($businesses)
            ->additional(['meta' => ['radius_km' => $request->radiusKm()]]);
    }
}
