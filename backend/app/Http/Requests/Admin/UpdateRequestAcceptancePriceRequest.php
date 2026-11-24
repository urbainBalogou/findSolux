<?php

namespace App\Http\Requests\Admin;

use App\Services\Settings\PlatformSettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateRequestAcceptancePriceRequest extends FormRequest
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
            PlatformSettings::REQUEST_ACCEPTANCE_PRICE_XOF => ['required', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    public function attributes(): array
    {
        return [
            PlatformSettings::REQUEST_ACCEPTANCE_PRICE_XOF => 'prix d’une demande acceptée',
        ];
    }
}
