<?php

namespace App\Http\Controllers;

use App\Enums\BusinessStatus;
use App\Models\Business;
use App\Models\OpeningHour;
use App\Support\PhoneNumber;
use Illuminate\View\View;

/**
 * Page web publique d'un garage (R4) : légère, partageable sur WhatsApp et
 * référencée par les moteurs de recherche. Utilisable sans l'application.
 */
class GaragePageController extends Controller
{
    public function __invoke(Business $business): View
    {
        abort_unless($business->status === BusinessStatus::Published, 404);

        $business->load([
            'district', 'city', 'vehicleTypes', 'services.service', 'services.vehicleType', 'openingHours', 'photos',
        ]);

        $hoursByDay = [];
        foreach (range(1, 7) as $day) {
            $hoursByDay[$day] = $business->openingHours
                ->where('day_of_week', $day)
                ->map(fn (OpeningHour $range) => substr($range->opens_at, 0, 5).'–'.substr($range->closes_at, 0, 5))
                ->values()
                ->all();
        }

        $whatsappMessage = "Bonjour {$business->name}, je vous ai trouvé sur findSolux.";

        return view('garages.show', [
            'business' => $business,
            'isOpen' => $business->isOpenAt(now()),
            'hoursByDay' => $hoursByDay,
            'today' => now(config('findsolux.timezone'))->dayOfWeekIso,
            'phoneDisplay' => PhoneNumber::display($business->phone),
            'whatsappUrl' => $business->whatsapp_phone
                ? 'https://wa.me/'.ltrim($business->whatsapp_phone, '+').'?text='.rawurlencode($whatsappMessage)
                : null,
            'directionsUrl' => 'https://www.google.com/maps/dir/?api=1&destination='.$business->latitude.','.$business->longitude,
            'downloadUrl' => config('findsolux.app_download_url'),
        ]);
    }
}
