<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Business\UpdateBusinessServicesRequest;
use App\Http\Resources\MyBusinessResource;
use App\Services\Offers\BusinessEntitlements;
use Illuminate\Support\Facades\DB;

class MyBusinessServicesController extends Controller
{
    /**
     * Tous les services sont enregistrés ; seuls ceux couverts par les packs
     * actifs sont publiés (ADR-015).
     */
    public function __invoke(UpdateBusinessServicesRequest $request, BusinessEntitlements $entitlements): MyBusinessResource
    {
        $business = $request->user()->business;

        DB::transaction(function () use ($request, $business, $entitlements) {
            $business->services()->delete();
            $business->services()->createMany($request->validated('services'));
            $entitlements->sync($business);
        });

        return new MyBusinessResource($business->load(MyBusinessResource::RELATIONS));
    }
}
