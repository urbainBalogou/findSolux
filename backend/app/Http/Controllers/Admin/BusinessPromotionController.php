<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BusinessStatus;
use App\Enums\DurationUnit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBusinessPromotionRequest;
use App\Http\Requests\Admin\UpdateBusinessPromotionRequest;
use App\Models\Business;
use App\Models\BusinessPromotion;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BusinessPromotionController extends Controller
{
    public function index(): View
    {
        $promotions = BusinessPromotion::query()
            ->with(['business:id,name,district_id,status', 'business.district:id,name'])
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $availableBusinesses = Business::query()
            ->select(['id', 'name', 'district_id'])
            ->with('district:id,name')
            ->where('status', BusinessStatus::Published)
            ->whereDoesntHave('promotion')
            ->orderBy('name')
            ->get();

        return view('admin.business-promotions.index', [
            'promotions' => $promotions,
            'availableBusinesses' => $availableBusinesses,
            'defaultStartsAt' => now(config('findsolux.timezone'))->format('Y-m-d\TH:i'),
        ]);
    }

    public function store(StoreBusinessPromotionRequest $request): RedirectResponse
    {
        BusinessPromotion::create($this->promotionData($request));

        return back()->with('success', 'Le garage a été mis en vedette.');
    }

    public function update(UpdateBusinessPromotionRequest $request, BusinessPromotion $businessPromotion): RedirectResponse
    {
        $businessPromotion->update($this->promotionData($request));

        return back()->with('success', 'La mise en vedette a été mise à jour.');
    }

    public function destroy(BusinessPromotion $businessPromotion): RedirectResponse
    {
        $businessPromotion->delete();

        return back()->with('success', 'Le garage a été retiré de la mise en vedette.');
    }

    /**
     * @return array<string, mixed>
     */
    private function promotionData(StoreBusinessPromotionRequest $request): array
    {
        $validated = $request->validated();
        $isUnlimited = $validated['duration_type'] === 'unlimited';
        $startsAt = CarbonImmutable::createFromFormat(
            'Y-m-d\TH:i',
            $validated['starts_at'],
            config('findsolux.timezone'),
        )->utc();
        $durationUnit = $isUnlimited ? null : DurationUnit::from($validated['duration_unit']);

        return [
            'business_id' => $validated['business_id'],
            'position' => $validated['position'],
            'starts_at' => $startsAt,
            'ends_at' => $isUnlimited ? null : $durationUnit->addTo($startsAt, $validated['duration_value']),
            'duration_value' => $isUnlimited ? null : $validated['duration_value'],
            'duration_unit' => $durationUnit,
            'is_unlimited' => $isUnlimited,
        ];
    }
}
