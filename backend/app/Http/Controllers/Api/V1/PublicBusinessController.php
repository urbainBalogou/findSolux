<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ContactRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PublicBusinessResource;
use App\Models\Business;
use App\Models\ContactRequest;
use Illuminate\Http\Request;

/**
 * Fiche publique d'un garage, accessible sans compte (C3). Le numéro n'est
 * donné qu'au client déjà en contact avec le garage (C4) : le jeton Sanctum
 * est lu s'il est envoyé, sans être exigé.
 */
class PublicBusinessController extends Controller
{
    public function __invoke(Request $request, Business $business): PublicBusinessResource
    {
        abort_unless($business->isVisible(), 404, 'Ce garage n’est plus visible.');

        $viewer = $request->user('sanctum');
        $isInContact = $viewer !== null && (
            $viewer->getKey() === $business->owner_user_id
            || $business->contacts()->where('client_user_id', $viewer->getKey())->exists()
        );
        $pendingRequest = $viewer === null || $isInContact ? null : ContactRequest::query()
            ->whereBelongsTo($business)
            ->where('client_user_id', $viewer->getKey())
            ->where('status', ContactRequestStatus::Pending)
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        return (new PublicBusinessResource($business->load(PublicBusinessResource::RELATIONS)))
            ->forViewer($isInContact, $pendingRequest);
    }
}
