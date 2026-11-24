<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BusinessContactResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * « Mes garages » : garages avec lesquels le client a échangé ses coordonnées (C6).
 */
class ContactController extends Controller
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $contacts = $request->user()->businessContacts()
            ->with(['business.photos'])
            ->latest('id')
            ->get();

        return BusinessContactResource::collection($contacts);
    }
}
