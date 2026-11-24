<?php

namespace App\Http\Requests\Business;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Modification partielle : seuls les champs envoyés sont modifiés.
 */
class UpdateBusinessRequest extends FormRequest
{
    use BusinessRules;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = $this->profileRules();

        foreach (['name', 'phone', 'district_id', 'vehicle_type_ids'] as $field) {
            array_unshift($rules[$field], 'sometimes', 'required');
        }
        array_unshift($rules['whatsapp_phone'], 'sometimes');
        array_unshift($rules['latitude'], 'required_with:longitude');
        array_unshift($rules['longitude'], 'required_with:latitude');

        return [
            ...$rules,
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'landmark' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_mobile' => ['sometimes', 'boolean'],
            'service_radius_km' => ['sometimes', 'nullable', 'integer', 'between:1,50'],
            'is_24_7' => ['sometimes', 'boolean'],
        ];
    }
}
