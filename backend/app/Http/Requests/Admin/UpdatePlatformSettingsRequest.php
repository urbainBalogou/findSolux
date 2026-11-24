<?php

namespace App\Http\Requests\Admin;

use App\Services\Settings\PlatformSettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdatePlatformSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::guard('admin')->check();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            PlatformSettings::DISTRESS_SIGNAL_WAIT_MINUTES => ['required', 'integer', 'min:1', 'max:120'],
            PlatformSettings::CONTACT_REQUEST_WAIT_HOURS => ['required', 'integer', 'min:1', 'max:168'],
        ];
    }

    public function attributes(): array
    {
        return [
            PlatformSettings::DISTRESS_SIGNAL_WAIT_MINUTES => 'délai d’attente d’un signal de détresse',
            PlatformSettings::CONTACT_REQUEST_WAIT_HOURS => 'délai de réponse à une demande de contact',
        ];
    }
}
