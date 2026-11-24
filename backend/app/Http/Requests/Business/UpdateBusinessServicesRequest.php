<?php

namespace App\Http\Requests\Business;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Remplace la liste complète des services d'une fiche.
 */
class UpdateBusinessServicesRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $business = $this->user()->business;

        return [
            'services' => ['required', 'array', 'min:1', 'max:60'],
            'services.*.service_id' => [
                'required',
                'integer',
                Rule::exists('services', 'id')->where('is_active', true)->where('trade_id', $business?->trade_id),
            ],
            // Un prix propre à un véhicule n'a de sens que pour un véhicule pris en charge.
            'services.*.vehicle_type_id' => [
                'nullable',
                'integer',
                Rule::in($business?->vehicleTypes()->pluck('vehicle_types.id')->all() ?? []),
            ],
            'services.*.price_min' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'services.*.price_max' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'services.*.description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'services.min' => 'Choisissez au moins un service.',
            'services.*.vehicle_type_id.in' => 'Ce type de véhicule ne fait pas partie de ceux que vous réparez.',
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $seen = [];
                foreach ($this->input('services') as $index => $service) {
                    $min = $service['price_min'] ?? null;
                    $max = $service['price_max'] ?? null;
                    if ($min !== null && $max !== null && $max < $min) {
                        $validator->errors()->add(
                            "services.{$index}.price_max",
                            'Le prix maximum doit être supérieur ou égal au prix minimum.',
                        );
                    }

                    $key = $service['service_id'].'-'.($service['vehicle_type_id'] ?? 'tous');
                    if (isset($seen[$key])) {
                        $validator->errors()->add("services.{$index}.service_id", 'Ce service figure deux fois.');
                    }
                    $seen[$key] = true;
                }
            },
        ];
    }
}
