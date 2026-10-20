<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BusinessStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PublicBusinessResource;
use App\Models\Business;

/**
 * Fiche publique d'un garage, accessible sans compte (C3).
 */
class PublicBusinessController extends Controller
{
    public function __invoke(Business $business): PublicBusinessResource
    {
        abort_unless($business->status === BusinessStatus::Published, 404, 'Ce garage n’est plus visible.');

        return new PublicBusinessResource($business->load(PublicBusinessResource::RELATIONS));
    }
}
