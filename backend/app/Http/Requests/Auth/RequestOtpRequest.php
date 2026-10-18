<?php

namespace App\Http\Requests\Auth;

use App\Rules\ValidPhoneNumber;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

class RequestOtpRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:30', new ValidPhoneNumber(config('otp.region'))],
        ];
    }

    /**
     * Numéro validé, au format E.164.
     */
    public function phone(): string
    {
        return PhoneNumber::normalize($this->validated('phone'), config('otp.region'), mobileOnly: true);
    }
}
