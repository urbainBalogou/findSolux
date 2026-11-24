<?php

namespace App\Http\Requests\Admin;

use App\Enums\DurationUnit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreBusinessPromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::guard('admin')->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $promotion = $this->route('business_promotion');
        $hasLimitedDuration = $this->input('duration_type') === 'limited';

        return [
            'business_id' => [
                'required',
                'integer',
                Rule::exists('businesses', 'id'),
                Rule::unique('business_promotions', 'business_id')->ignore($promotion),
            ],
            'position' => ['required', 'integer', 'min:1', 'max:10000'],
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'duration_type' => ['required', Rule::in(['limited', 'unlimited'])],
            'duration_value' => [
                Rule::requiredIf($hasLimitedDuration),
                Rule::prohibitedIf(! $hasLimitedDuration),
                'nullable',
                'integer',
                'min:1',
                'max:10000',
            ],
            'duration_unit' => [
                Rule::requiredIf($hasLimitedDuration),
                Rule::prohibitedIf(! $hasLimitedDuration),
                'nullable',
                Rule::enum(DurationUnit::class),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'business_id' => 'garage',
            'position' => 'rang',
            'starts_at' => 'date de début',
            'duration_type' => 'type de durée',
            'duration_value' => 'durée',
            'duration_unit' => 'unité de durée',
        ];
    }
}
