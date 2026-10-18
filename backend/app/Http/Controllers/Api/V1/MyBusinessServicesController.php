<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Business\UpdateBusinessServicesRequest;
use App\Http\Resources\MyBusinessResource;
use Illuminate\Support\Facades\DB;

class MyBusinessServicesController extends Controller
{
    public function __invoke(UpdateBusinessServicesRequest $request): MyBusinessResource
    {
        $business = $request->user()->business;

        DB::transaction(function () use ($request, $business) {
            $business->services()->delete();
            $business->services()->createMany($request->validated('services'));
        });

        return new MyBusinessResource($business->load(MyBusinessResource::RELATIONS));
    }
}
