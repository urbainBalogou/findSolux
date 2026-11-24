<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Business\StoreSubscriptionRequest;
use App\Http\Requests\Business\UpdateSubscriptionRequest;
use App\Http\Resources\BusinessSubscriptionResource;
use App\Http\Resources\EntitlementsResource;
use App\Models\Business;
use App\Models\BusinessSubscription;
use App\Models\CommercialOffer;
use App\Services\Offers\RequestPricing;
use App\Services\Offers\SubscriptionService;
use App\Services\Wallets\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Offres du garage de l'utilisateur connecté, achetées depuis son portefeuille (P4).
 */
class MyBusinessSubscriptionController extends Controller
{
    /**
     * Offres actives (en cours ou programmées), puis les 10 dernières terminées.
     */
    public function index(Request $request, RequestPricing $pricing, WalletService $wallets): AnonymousResourceCollection
    {
        $business = $request->user()->business;

        $active = $business->subscriptions()
            ->where('status', SubscriptionStatus::Active)
            ->orderBy('offer_type')
            ->orderBy('starts_at')
            ->get();
        $ended = $business->subscriptions()
            ->where('status', '!=', SubscriptionStatus::Active)
            ->latest('ends_at')
            ->limit(10)
            ->get();

        return BusinessSubscriptionResource::collection($active->concat($ended))
            ->additional(['meta' => $this->meta($request, $business, $pricing, $wallets)]);
    }

    public function store(
        StoreSubscriptionRequest $request,
        SubscriptionService $subscriptions,
        RequestPricing $pricing,
        WalletService $wallets,
    ): JsonResponse {
        $business = $request->user()->business;

        $subscription = $subscriptions->purchase(
            $business,
            CommercialOffer::findOrFail($request->validated('offer_id')),
            $request->boolean('auto_renew'),
        );

        return (new BusinessSubscriptionResource($subscription))
            ->additional(['meta' => $this->meta($request, $business->refresh(), $pricing, $wallets)])
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        UpdateSubscriptionRequest $request,
        BusinessSubscription $subscription,
        SubscriptionService $subscriptions,
    ): BusinessSubscriptionResource {
        abort_unless($subscription->business_id === $request->user()->business->getKey(), 404, 'Élément introuvable.');

        return new BusinessSubscriptionResource(
            $subscriptions->setAutoRenew($subscription, $request->boolean('auto_renew')),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function meta(Request $request, Business $business, RequestPricing $pricing, WalletService $wallets): array
    {
        return [
            'entitlements' => new EntitlementsResource($business->load('services')),
            'acceptance_price' => ['amount' => $pricing->acceptancePriceXof(), 'currency' => 'XOF'],
            'balance' => ['amount' => $wallets->balanceOf($request->user()), 'currency' => 'XOF'],
        ];
    }
}
