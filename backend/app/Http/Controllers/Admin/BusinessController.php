<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CommercialOfferType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GrantSubscriptionRequest;
use App\Models\Business;
use App\Models\CommercialOffer;
use App\Services\Offers\SubscriptionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Garages inscrits : état de l'adhésion, solde, octroi manuel d'offres (§3.3).
 */
class BusinessController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim($request->string('q')->toString());

        $businesses = Business::query()
            ->with(['district:id,name', 'owner:id,phone', 'owner.wallet:id,user_id,balance_xof'])
            ->when($search !== '', fn (Builder $query) => $query->where(
                fn (Builder $match) => $match
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', '%'.preg_replace('/\D/', '', $search).'%'),
            ))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.businesses.index', [
            'businesses' => $businesses,
            'search' => $search,
            'grantableOffers' => CommercialOffer::query()
                ->whereIn('type', [CommercialOfferType::Membership, CommercialOfferType::ServiceBundle, CommercialOfferType::StaffBundle])
                ->orderBy('type')
                ->orderBy('position')
                ->get(),
        ]);
    }

    /**
     * Offre accordée sans débit (offre de lancement, geste commercial).
     */
    public function grant(GrantSubscriptionRequest $request, Business $business, SubscriptionService $subscriptions): RedirectResponse
    {
        $offer = CommercialOffer::findOrFail($request->validated('offer_id'));
        $subscriptions->grant($business, $offer, Auth::guard('admin')->user());

        return back()->with('success', "« {$offer->name} » a été accordée à {$business->name}.");
    }
}
