<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateRequestAcceptancePriceRequest;
use App\Services\Settings\PlatformSettings;
use Illuminate\Http\RedirectResponse;

/**
 * Prix unique débité au garage pour chaque demande acceptée (§4.3), réglé
 * depuis « Tarifs et offres ».
 */
class RequestAcceptancePriceController extends Controller
{
    public function __invoke(UpdateRequestAcceptancePriceRequest $request, PlatformSettings $settings): RedirectResponse
    {
        $settings->update([
            PlatformSettings::REQUEST_ACCEPTANCE_PRICE_XOF => (int) $request->validated(PlatformSettings::REQUEST_ACCEPTANCE_PRICE_XOF),
        ]);

        return back()->with('success', 'Le prix d’une demande acceptée a été enregistré.');
    }
}
