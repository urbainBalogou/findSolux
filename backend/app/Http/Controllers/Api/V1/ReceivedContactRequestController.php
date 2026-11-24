<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReceivedContactRequestResource;
use App\Models\ContactRequest;
use App\Services\Offers\RequestPricing;
use App\Services\Requests\ContactRequestService;
use App\Services\Wallets\WalletService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Demandes de contact reçues par le garage (P5) : vues et acceptées par le
 * garagiste ou un membre actif de son équipe, débitées du portefeuille du garagiste.
 */
class ReceivedContactRequestController extends Controller
{
    public function index(Request $request, RequestPricing $pricing, WalletService $wallets): AnonymousResourceCollection
    {
        $business = $request->user()->workplace();
        $contactRequests = ContactRequest::query()
            ->whereBelongsTo($business)
            ->with(['client', 'acceptedBy'])
            ->latest('id')
            ->limit(50)
            ->get();

        return ReceivedContactRequestResource::collection($contactRequests)->additional(['meta' => [
            'acceptance_price' => ['amount' => $pricing->acceptancePriceXof(), 'currency' => 'XOF'],
            'balance' => ['amount' => $wallets->balanceOf($business->owner), 'currency' => 'XOF'],
        ]]);
    }

    public function accept(Request $request, ContactRequest $contactRequest, ContactRequestService $contactRequests): ReceivedContactRequestResource
    {
        $business = $request->user()->workplace();
        $this->ensureReceivedBy($request, $contactRequest);

        return new ReceivedContactRequestResource(
            $contactRequests->accept($contactRequest, $business, $request->user())->load(['client', 'acceptedBy']),
        );
    }

    public function decline(Request $request, ContactRequest $contactRequest, ContactRequestService $contactRequests): ReceivedContactRequestResource
    {
        $this->ensureReceivedBy($request, $contactRequest);

        return new ReceivedContactRequestResource($contactRequests->decline($contactRequest)->load(['client', 'acceptedBy']));
    }

    private function ensureReceivedBy(Request $request, ContactRequest $contactRequest): void
    {
        abort_unless($contactRequest->business_id === $request->user()->workplace()->getKey(), 404, 'Élément introuvable.');
    }
}
