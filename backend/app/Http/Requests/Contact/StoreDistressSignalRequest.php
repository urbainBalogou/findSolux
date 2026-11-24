<?php

namespace App\Http\Requests\Contact;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDistressSignalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        [$minLat, $maxLat] = config('findsolux.country_bounds.latitude');
        [$minLng, $maxLng] = config('findsolux.country_bounds.longitude');

        return [
            'message' => ['required', 'string', 'min:3', 'max:500'],
            'latitude' => ['required', 'numeric', "between:{$minLat},{$maxLat}"],
            'longitude' => ['required', 'numeric', "between:{$minLng},{$maxLng}"],
            'service_id' => ['nullable', 'integer', Rule::exists('services', 'id')],
            'vehicle_type_id' => ['nullable', 'integer', Rule::exists('vehicle_types', 'id')->where('is_active', true)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'message.required' => 'Décrivez votre problème en quelques mots.',
            'latitude.between' => 'Votre position doit se trouver au Togo.',
            'longitude.between' => 'Votre position doit se trouver au Togo.',
        ];
    }

    /**
     * @return array{message: string, latitude: float, longitude: float, service_id: ?int, vehicle_type_id: ?int}
     */
    public function signalData(): array
    {
        return [
            'message' => trim($this->string('message')->toString()),
            'latitude' => (float) $this->validated('latitude'),
            'longitude' => (float) $this->validated('longitude'),
            'service_id' => $this->validated('service_id'),
            'vehicle_type_id' => $this->validated('vehicle_type_id'),
        ];
    }
}
