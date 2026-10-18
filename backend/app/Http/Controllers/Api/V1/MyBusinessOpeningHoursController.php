<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Business\UpdateOpeningHoursRequest;
use App\Http\Resources\MyBusinessResource;
use Illuminate\Support\Facades\DB;

class MyBusinessOpeningHoursController extends Controller
{
    public function __invoke(UpdateOpeningHoursRequest $request): MyBusinessResource
    {
        $business = $request->user()->business;

        DB::transaction(function () use ($request, $business) {
            $business->openingHours()->delete();
            $business->openingHours()->createMany(array_map(fn (array $range) => [
                'day_of_week' => $range['day'],
                'opens_at' => $range['opens_at'],
                'closes_at' => $range['closes_at'],
            ], $request->validated('opening_hours')));
        });

        return new MyBusinessResource($business->load(MyBusinessResource::RELATIONS));
    }
}
