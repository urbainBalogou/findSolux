<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contact\StoreContactRequestRequest;
use App\Http\Resources\ContactRequestResource;
use App\Models\Business;
use App\Models\ContactRequest;
use App\Services\Requests\ContactRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Demandes de contact du client connecté (C4).
 */
class ContactRequestController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $contactRequests = ContactRequest::query()
            ->where('client_user_id', $request->user()->getKey())
            ->with('business')
            ->latest('id')
            ->limit(30)
            ->get();

        return ContactRequestResource::collection($contactRequests);
    }

    /**
     * 201 pour une nouvelle demande, 200 si une demande attend déjà la réponse du garage.
     */
    public function store(
        StoreContactRequestRequest $request,
        Business $business,
        ContactRequestService $contactRequests,
    ): JsonResponse {
        abort_unless($business->isVisible(), 404, 'Ce garage n’est plus visible.');

        [$contactRequest, $isNew] = $contactRequests->request(
            $request->user(),
            $business,
            $request->validated('message'),
        );

        return (new ContactRequestResource($contactRequest->setRelation('business', $business)))
            ->response()
            ->setStatusCode($isNew ? 201 : 200);
    }

    public function cancel(Request $request, ContactRequest $contactRequest, ContactRequestService $contactRequests): ContactRequestResource
    {
        abort_unless($contactRequest->client_user_id === $request->user()->getKey(), 404, 'Élément introuvable.');

        return new ContactRequestResource($contactRequests->cancel($contactRequest)->load('business'));
    }
}
