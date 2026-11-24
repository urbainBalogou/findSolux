<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CommercialOfferType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCommercialOfferRequest;
use App\Http\Requests\Admin\UpdateCommercialOfferRequest;
use App\Models\CommercialOffer;
use App\Services\Settings\PlatformSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CommercialOfferController extends Controller
{
    public function index(PlatformSettings $settings): View
    {
        $offersByType = CommercialOffer::query()
            ->orderBy('type')
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (CommercialOffer $offer): string => $offer->type->value);

        return view('admin.commercial-offers.index', [
            'offerTypes' => CommercialOfferType::cases(),
            'offersByType' => $offersByType,
            'requestAcceptancePrice' => $settings->requestAcceptancePriceXof(),
        ]);
    }

    public function store(StoreCommercialOfferRequest $request): RedirectResponse
    {
        CommercialOffer::create($this->offerData($request));

        return back()->with('success', 'L’offre a été créée.');
    }

    public function update(UpdateCommercialOfferRequest $request, CommercialOffer $commercialOffer): RedirectResponse
    {
        $commercialOffer->update($this->offerData($request));

        return back()->with('success', 'L’offre a été mise à jour.');
    }

    /**
     * @return array<string, mixed>
     */
    private function offerData(StoreCommercialOfferRequest $request): array
    {
        $validated = $request->validated();
        $isUnlimited = $validated['duration_type'] === 'unlimited';
        $type = CommercialOfferType::from($validated['type']);

        return [
            'type' => $type,
            'name' => $validated['name'],
            'quantity' => $type->requiresQuantity() ? $validated['quantity'] : null,
            'price_xof' => $validated['price_xof'],
            'duration_value' => $isUnlimited ? null : $validated['duration_value'],
            'duration_unit' => $isUnlimited ? null : $validated['duration_unit'],
            'is_unlimited' => $isUnlimited,
            'is_active' => $request->boolean('is_active'),
            'position' => $validated['position'] ?? 0,
        ];
    }
}
