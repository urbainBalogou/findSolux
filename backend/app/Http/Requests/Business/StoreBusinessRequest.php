<?php

namespace App\Http\Requests\Business;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Inscription express (moins de 5 minutes) : le strict nécessaire pour
 * apparaître dans les recherches. Le reste de la fiche se complète plus tard.
 */
class StoreBusinessRequest extends FormRequest
{
    use BusinessRules;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = $this->profileRules();

        foreach (['name', 'phone', 'district_id', 'latitude', 'longitude', 'vehicle_type_ids'] as $field) {
            array_unshift($rules[$field], 'required');
        }

        return [
            ...$rules,
            'trade_id' => ['required', 'integer', Rule::exists('trades', 'id')->where('is_active', true)],
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('services', 'id')
                    ->where('is_active', true)
                    ->where('trade_id', $this->integer('trade_id')),
            ],
        ];
    }
}
