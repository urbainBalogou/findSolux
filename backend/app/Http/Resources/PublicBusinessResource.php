<?php

namespace App\Http\Resources;

use App\Models\Business;
use App\Models\BusinessPhoto;
use App\Models\BusinessService;
use App\Models\OpeningHour;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fiche publique d'un garage (C3), vue par les clients. Les noms de
 * quartiers, services et véhicules sont résolus par l'application depuis les
 * référentiels.
 *
 * @mixin Business
 */
class PublicBusinessResource extends JsonResource
{
    public const RELATIONS = ['vehicleTypes', 'services', 'openingHours', 'photos'];

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'city_id' => $this->city_id,
            'district_id' => $this->district_id,
            'landmark' => $this->landmark,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'phone' => $this->phone,
            'whatsapp_phone' => $this->whatsapp_phone,
            'is_mobile' => $this->is_mobile,
            'service_radius_km' => $this->service_radius_km,
            'is_24_7' => $this->is_24_7,
            'is_open_now' => $this->isOpenAt(now()),
            'has_opening_hours' => $this->openingHours->isNotEmpty(),
            'vehicle_type_ids' => $this->vehicleTypes->pluck('id')->sort()->values(),
            'services' => $this->services->map(fn (BusinessService $service) => [
                'service_id' => $service->service_id,
                'vehicle_type_id' => $service->vehicle_type_id,
                'price_min' => $service->price_min,
                'price_max' => $service->price_max,
                'description' => $service->description,
            ]),
            'opening_hours' => $this->openingHours->map(fn (OpeningHour $range) => [
                'day' => $range->day_of_week,
                'opens_at' => substr($range->opens_at, 0, 5),
                'closes_at' => substr($range->closes_at, 0, 5),
            ]),
            'photos' => $this->photos->map(fn (BusinessPhoto $photo) => [
                'url' => $photo->url(),
                'thumbnail_url' => $photo->thumbnailUrl(),
                'width' => $photo->width,
                'height' => $photo->height,
            ]),
            // Page web publique (R4), à partager sur WhatsApp.
            'share_url' => route('garages.show', $this->slug),
        ];
    }
}
