<?php

namespace App\Http\Requests\Admin;

use App\Enums\CommercialOfferType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class GrantSubscriptionRequest extends FormRequest
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
            'offer_id' => [
                'required',
                'integer',
                Rule::exists('commercial_offers', 'id')->whereIn('type', [
                    CommercialOfferType::Membership->value,
                    CommercialOfferType::ServiceBundle->value,
                    CommercialOfferType::StaffBundle->value,
                ]),
            ],
        ];
    }

    public function attributes(): array
    {
        return ['offer_id' => 'offre'];
    }
}
