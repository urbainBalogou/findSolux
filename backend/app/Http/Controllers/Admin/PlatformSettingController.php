<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePlatformSettingsRequest;
use App\Services\Settings\PlatformSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Paramètres de la plateforme : délais des demandes des clients.
 */
class PlatformSettingController extends Controller
{
    public function edit(PlatformSettings $settings): View
    {
        return view('admin.settings.edit', [
            'distressSignalWaitMinutes' => $settings->distressSignalWaitMinutes(),
            'contactRequestWaitHours' => $settings->contactRequestWaitHours(),
        ]);
    }

    public function update(UpdatePlatformSettingsRequest $request, PlatformSettings $settings): RedirectResponse
    {
        $settings->update(array_map('intval', $request->validated()));

        return back()->with('success', 'Les paramètres ont été enregistrés.');
    }
}
