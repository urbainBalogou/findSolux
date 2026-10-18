<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BusinessStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Business\StoreBusinessRequest;
use App\Http\Requests\Business\UpdateBusinessRequest;
use App\Http\Resources\MyBusinessResource;
use App\Models\Business;
use App\Models\District;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Fiche du garage de l'utilisateur connecté (espace garagiste).
 */
class MyBusinessController extends Controller
{
    /**
     * `data` vaut null tant que l'utilisateur n'a pas inscrit de garage.
     */
    public function show(Request $request): JsonResponse
    {
        $business = $request->user()->business?->load(MyBusinessResource::RELATIONS);

        return response()->json(['data' => $business ? new MyBusinessResource($business) : null]);
    }

    public function store(StoreBusinessRequest $request): JsonResponse
    {
        $district = District::findOrFail($request->validated('district_id'));
        $whatsapp = $request->phoneNumber('whatsapp_phone');

        try {
            $business = DB::transaction(function () use ($request, $district, $whatsapp) {
                $business = new Business([
                    ...$request->safe()->only(['trade_id', 'name', 'latitude', 'longitude']),
                    'phone' => $request->phoneNumber('phone'),
                    'whatsapp_phone' => $whatsapp,
                    'city_id' => $district->city_id,
                    'district_id' => $district->id,
                ]);
                $business->owner()->associate($request->user());
                $business->slug = Business::uniqueSlug($business->name, $district);
                // Visible tout de suite : la vérification (badge) est indépendante.
                $business->status = BusinessStatus::Published;
                $business->save();

                $business->vehicleTypes()->sync($request->validated('vehicle_type_ids'));
                $business->services()->createMany(
                    array_map(fn (int $id) => ['service_id' => $id], $request->validated('service_ids')),
                );

                return $business;
            });
        } catch (UniqueConstraintViolationException) {
            return response()->json(['message' => 'Vous avez déjà inscrit un garage.'], 409);
        }

        return (new MyBusinessResource($business->load(MyBusinessResource::RELATIONS)))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateBusinessRequest $request): MyBusinessResource
    {
        $business = $request->user()->business;
        $data = $request->safe()->except(['phone', 'whatsapp_phone', 'vehicle_type_ids']);

        foreach (['phone', 'whatsapp_phone'] as $key) {
            if ($request->has($key)) {
                $data[$key] = $request->phoneNumber($key);
            }
        }
        if (isset($data['district_id'])) {
            $data['city_id'] = District::findOrFail($data['district_id'])->city_id;
        }
        if (($data['is_mobile'] ?? null) === false) {
            $data['service_radius_km'] = null;
        }

        DB::transaction(function () use ($request, $business, $data) {
            $business->update($data);

            if ($request->has('vehicle_type_ids')) {
                $vehicleTypeIds = $request->validated('vehicle_type_ids');
                $business->vehicleTypes()->sync($vehicleTypeIds);
                // Les prix propres à un véhicule qui n'est plus réparé disparaissent.
                $business->services()
                    ->whereNotNull('vehicle_type_id')
                    ->whereNotIn('vehicle_type_id', $vehicleTypeIds)
                    ->delete();
            }
        });

        return new MyBusinessResource($business->load(MyBusinessResource::RELATIONS));
    }
}
