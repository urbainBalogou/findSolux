<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BusinessContactResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * « Mes clients » : clients avec lesquels le garage a échangé ses coordonnées (P6).
 */
class MyBusinessClientController extends Controller
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $contacts = $request->user()->workplace()->contacts()
            ->with('client')
            ->latest('id')
            ->get();

        return BusinessContactResource::collection($contacts);
    }
}
