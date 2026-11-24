<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeviceTokenRequest;
use App\Models\DeviceToken;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Jetons Firebase de l'appareil, pour les notifications push (ADR-016).
 */
class DeviceTokenController extends Controller
{
    /**
     * Un appareil appartient au dernier compte connecté dessus.
     */
    public function store(StoreDeviceTokenRequest $request): Response
    {
        DeviceToken::query()->updateOrCreate(
            ['token' => $request->validated('token')],
            ['user_id' => $request->user()->getKey(), 'platform' => $request->validated('platform')],
        );

        return response()->noContent();
    }

    /**
     * À la déconnexion : l'appareil ne reçoit plus les notifications de ce compte.
     */
    public function destroy(Request $request): Response
    {
        $request->validate(['token' => ['required', 'string', 'max:255']]);

        $request->user()->deviceTokens()->where('token', $request->string('token')->toString())->delete();

        return response()->noContent();
    }
}
