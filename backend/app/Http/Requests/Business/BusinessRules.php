<?php

namespace App\Http\Requests\Business;

use App\Models\City;
use App\Rules\ValidPhoneNumber;
use App\Support\PhoneNumber;
use Illuminate\Validation\Rule;

/**
 * Règles communes à la création et à la modification d'une fiche.
 */
trait BusinessRules
{
    /**
     * @return array<string, list<mixed>>
     */
    protected function profileRules(): array
    {
        $region = config('otp.region');
        [$minLat, $maxLat] = config('findsolux.country_bounds.latitude');
        [$minLng, $maxLng] = config('findsolux.country_bounds.longitude');

        return [
            'name' => ['string', 'min:2', 'max:120'],
            // Numéro d'appel : un fixe est accepté, contrairement à WhatsApp.
            'phone' => ['string', 'max:30', new ValidPhoneNumber($region, mobileOnly: false)],
            'whatsapp_phone' => ['nullable', 'string', 'max:30', new ValidPhoneNumber($region)],
            'district_id' => [
                'integer',
                Rule::exists('districts', 'id')
                    ->where('is_active', true)
                    ->whereIn('city_id', City::active()->pluck('id')->all()),
            ],
            'latitude' => ['numeric', "between:{$minLat},{$maxLat}"],
            'longitude' => ['numeric', "between:{$minLng},{$maxLng}"],
            'vehicle_type_ids' => ['array', 'min:1'],
            'vehicle_type_ids.*' => ['integer', 'distinct', Rule::exists('vehicle_types', 'id')->where('is_active', true)],
        ];
    }

    /**
     * Numéro validé au format E.164, ou null si le champ est vide.
     */
    public function phoneNumber(string $key): ?string
    {
        $value = $this->validated($key);

        return $value === null ? null : PhoneNumber::normalize($value, config('otp.region'));
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'latitude.between' => 'La position doit se trouver au Togo. Reprenez la position sur place.',
            'longitude.between' => 'La position doit se trouver au Togo. Reprenez la position sur place.',
            'vehicle_type_ids.required' => 'Choisissez au moins un type de véhicule.',
            'vehicle_type_ids.min' => 'Choisissez au moins un type de véhicule.',
            'service_ids.required' => 'Choisissez au moins un service.',
            'service_ids.min' => 'Choisissez au moins un service.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nom du garage',
            'phone' => "numéro d'appel",
            'whatsapp_phone' => 'numéro WhatsApp',
            'district_id' => 'quartier',
            'latitude' => 'position',
            'longitude' => 'position',
            'description' => 'description',
            'landmark' => 'point de repère',
            'service_radius_km' => "rayon d'intervention",
        ];
    }
}
