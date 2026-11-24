<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\OpeningHour;
use Illuminate\View\View;

/**
 * Page web publique d'un garage (R4) : légère, partageable sur WhatsApp et
 * référencée par les moteurs de recherche. Utilisable sans l'application.
 */
class GaragePageController extends Controller
{
    public function __invoke(Business $business): View
    {
        abort_unless($business->isVisible(), 404);

        $business->load([
            'district', 'city', 'vehicleTypes', 'publishedServices.service', 'publishedServices.vehicleType',
            'openingHours', 'photos',
        ]);

        $hoursByDay = [];
        foreach (range(1, 7) as $day) {
            $hoursByDay[$day] = $business->openingHours
                ->where('day_of_week', $day)
                ->map(fn (OpeningHour $range) => substr($range->opens_at, 0, 5).'–'.substr($range->closes_at, 0, 5))
                ->values()
                ->all();
        }

        // Le numéro n'est pas publié : le contact se demande dans l'application (C4).
        return view('garages.show', [
            'business' => $business,
            'isOpen' => $business->isOpenAt(now()),
            'hoursByDay' => $hoursByDay,
            'today' => now(config('findsolux.timezone'))->dayOfWeekIso,
            'directionsUrl' => 'https://www.google.com/maps/dir/?api=1&destination='.$business->latitude.','.$business->longitude,
            'downloadUrl' => config('findsolux.app_download_url'),
        ]);
    }
}
