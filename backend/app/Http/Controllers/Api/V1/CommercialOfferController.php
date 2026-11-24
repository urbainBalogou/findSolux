<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommercialOfferResource;
use App\Models\CommercialOffer;
use App\Services\Offers\RequestPricing;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CommercialOfferController extends Controller
{
    /**
     * Catalogue public, avec le prix unique d'une demande acceptée (§4.3).
     */
    public function __invoke(RequestPricing $pricing): AnonymousResourceCollection
    {
        $offers = CommercialOffer::query()
            ->where('is_active', true)
            ->get()
            ->sortBy(fn (CommercialOffer $offer): array => [
                $offer->type->sortOrder(),
                $offer->position,
                $offer->id,
            ])
            ->values();

        return CommercialOfferResource::collection($offers)->additional([
            'meta' => [
                'acceptance_price' => ['amount' => $pricing->acceptancePriceXof(), 'currency' => 'XOF'],
            ],
        ]);
    }
}
