<?php

namespace App\Http\Requests\Admin;

use App\Enums\CommercialOfferType;
use App\Enums\DurationUnit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreCommercialOfferRequest extends FormRequest
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
        $type = CommercialOfferType::tryFrom((string) $this->input('type'));
        $requiresQuantity = $type?->requiresQuantity() ?? false;
        $hasLimitedDuration = $this->input('duration_type') === 'limited';

        return [
            'type' => ['required', Rule::enum(CommercialOfferType::class)],
            'name' => ['required', 'string', 'max:100'],
            'quantity' => [
                Rule::requiredIf($requiresQuantity),
                Rule::prohibitedIf(! $requiresQuantity),
                'nullable',
                'integer',
                'min:1',
                'max:10000',
            ],
            'price_xof' => ['required', 'integer', 'min:0', 'max:100000000'],
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
            'position' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'type' => 'type d’offre',
            'name' => 'nom de l’offre',
            'quantity' => 'quantité incluse',
            'price_xof' => 'prix',
            'duration_type' => 'type de durée',
            'duration_value' => 'durée',
            'duration_unit' => 'unité de durée',
            'position' => 'ordre d’affichage',
            'is_active' => 'état actif',
        ];
    }
}
