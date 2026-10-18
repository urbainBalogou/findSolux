<?php

namespace App\Http\Resources;

use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Garage dans les résultats de recherche (cartes sous la carte). Les noms de
 * quartiers, services et véhicules sont résolus par l'application depuis les
 * référentiels.
 *
 * @mixin Business
 */
class BusinessSearchResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'district_id' => $this->district_id,
            'landmark' => $this->landmark,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'distance_m' => (int) round($this->distance_m),
            'is_open_now' => (bool) $this->is_open_now,
            'is_mobile' => $this->is_mobile,
            'is_24_7' => $this->is_24_7,
            'phone' => $this->phone,
            'whatsapp_phone' => $this->whatsapp_phone,
            'vehicle_type_ids' => $this->vehicleTypes->pluck('id')->sort()->values(),
            'service_ids' => $this->services->pluck('service_id')->unique()->sort()->values(),
            'photo_thumbnail_url' => $this->photos->first()?->thumbnailUrl(),
        ];
    }
}
