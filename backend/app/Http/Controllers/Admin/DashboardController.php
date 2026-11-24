<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BusinessStatus;
use App\Enums\ContactRequestStatus;
use App\Enums\DistressSignalStatus;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessPromotion;
use App\Models\CommercialOffer;
use App\Models\ContactRequest;
use App\Models\DistressSignal;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $lastWeek = now()->subDays(7);

        return view('admin.dashboard', [
            'publishedBusinessesCount' => Business::where('status', BusinessStatus::Published)->count(),
            'visibleBusinessesCount' => Business::query()->visible()->count(),
            'activeOffersCount' => CommercialOffer::where('is_active', true)->count(),
            'featuredBusinessesCount' => BusinessPromotion::active()->count(),
            'distressSignalsCount' => DistressSignal::where('created_at', '>=', $lastWeek)->count(),
            'acceptedDistressSignalsCount' => DistressSignal::where('created_at', '>=', $lastWeek)
                ->where('status', DistressSignalStatus::Accepted)
                ->count(),
            'acceptedContactRequestsCount' => ContactRequest::where('created_at', '>=', $lastWeek)
                ->where('status', ContactRequestStatus::Accepted)
                ->count(),
        ]);
    }
}
