<?php

namespace App\Http\Requests;

use App\Rules\ValidPhoneNumber;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            // Numéro où le garage rappellera le client : un mobile, pour WhatsApp.
            'phone' => ['sometimes', 'required', 'string', 'max:30', new ValidPhoneNumber(config('findsolux.phone_region'))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nom',
            'phone' => 'numéro de téléphone',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! is_string($this->input('phone'))) {
            return;
        }

        $normalized = PhoneNumber::normalize($this->input('phone'), config('findsolux.phone_region'), true);

        if ($normalized !== null) {
            $this->merge(['phone' => $normalized]);
        }
    }
}
