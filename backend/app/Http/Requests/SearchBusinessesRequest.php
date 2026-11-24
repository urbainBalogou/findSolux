<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchBusinessesRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        [$minLat, $maxLat] = config('findsolux.country_bounds.latitude');
        [$minLng, $maxLng] = config('findsolux.country_bounds.longitude');

        return [
            'latitude' => ['required', 'numeric', "between:{$minLat},{$maxLat}"],
            'longitude' => ['required', 'numeric', "between:{$minLng},{$maxLng}"],
            'radius_km' => ['nullable', 'integer', 'between:1,'.config('findsolux.search.max_radius_km')],
            'vehicle_type_id' => ['nullable', 'integer', Rule::exists('vehicle_types', 'id')->where('is_active', true)],
            'service_id' => ['nullable', 'integer', Rule::exists('services', 'id')->where('is_active', true)],
            'open_now' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'between:1,'.config('findsolux.search.max_results')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'latitude.between' => 'La recherche est disponible au Togo uniquement.',
            'longitude.between' => 'La recherche est disponible au Togo uniquement.',
        ];
    }

    public function radiusKm(): int
    {
        return $this->integer('radius_km', config('findsolux.search.default_radius_km'));
    }

    public function limit(): int
    {
        return $this->integer('limit', config('findsolux.search.max_results'));
    }

    /**
     * @return array{vehicle_type_id: ?int, service_id: ?int, open_now: bool}
     */
    public function filters(): array
    {
        return [
            'vehicle_type_id' => $this->validated('vehicle_type_id') === null ? null : $this->integer('vehicle_type_id'),
            'service_id' => $this->validated('service_id') === null ? null : $this->integer('service_id'),
            'open_now' => $this->boolean('open_now'),
        ];
    }
}
