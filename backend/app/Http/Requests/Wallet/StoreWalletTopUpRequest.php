<?php

namespace App\Http\Requests\Wallet;

use App\Enums\WalletPaymentMethod;
use App\Rules\ValidPhoneNumber;
use App\Support\PhoneNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWalletTopUpRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => [
                'required',
                'integer',
                'min:'.config('services.fedapay.minimum_top_up_xof', 100),
                'max:'.config('services.fedapay.maximum_top_up_xof', 1_000_000),
            ],
            'payment_method' => ['required', Rule::enum(WalletPaymentMethod::class)],
            'phone' => ['required', 'string', new ValidPhoneNumber('TG')],
        ];
    }

    public function paymentMethod(): WalletPaymentMethod
    {
        return WalletPaymentMethod::from($this->string('payment_method')->toString());
    }

    protected function prepareForValidation(): void
    {
        if (! is_string($this->input('phone'))) {
            return;
        }

        $normalized = PhoneNumber::normalize($this->input('phone'), 'TG', true);
        if ($normalized !== null) {
            $this->merge(['phone' => $normalized]);
        }
    }
}
