<?php

namespace App\Http\Requests\Auth;

class VerifyOtpRequest extends RequestOtpRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'code' => ['required', 'string', 'digits:'.config('otp.length')],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }
}
