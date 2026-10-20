<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BusinessStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\RecordBusinessEventRequest;
use App\Models\Business;
use App\Models\BusinessEvent;
use Illuminate\Http\Response;

/**
 * Vue ou clic sur une fiche (Appeler, WhatsApp, Itinéraire, Partager), pour
 * les statistiques du garagiste (P6). Accessible sans compte.
 */
class BusinessEventController extends Controller
{
    public function __invoke(RecordBusinessEventRequest $request, Business $business): Response
    {
        abort_unless($business->status === BusinessStatus::Published, 404, 'Ce garage n’est plus visible.');

        $user = $request->user('sanctum');

        // Ni le garagiste qui consulte sa propre fiche, ni les robots des
        // moteurs de recherche ne gonflent les statistiques.
        $isBot = preg_match('/bot|crawl|spider|slurp|preview|facebookexternalhit/i', (string) $request->userAgent()) === 1;

        if (! $isBot && $user?->id !== $business->owner_user_id) {
            // Un appareil compte une fois par jour et par action (index unique).
            BusinessEvent::insertOrIgnore([
                'business_id' => $business->id,
                'type' => $request->validated('type'),
                'device_id' => $request->validated('device_id'),
                'user_id' => $user?->id,
                'occurred_on' => now(config('findsolux.timezone'))->toDateString(),
                'created_at' => now(),
            ]);
        }

        return response()->noContent();
    }
}
