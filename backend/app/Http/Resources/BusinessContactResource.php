<?php

namespace App\Http\Resources;

use App\Models\BusinessContact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Contact échangé, vu par le client (« Mes garages ») ou par le garage (« Mes
 * clients ») : chacun voit l'autre partie.
 *
 * @mixin BusinessContact
 */
class BusinessContactResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'source' => $this->source->value,
            'created_at' => $this->created_at->toIso8601String(),
            'garage' => $this->whenLoaded('business', fn () => [
                'slug' => $this->business->slug,
                'name' => $this->business->name,
                'district_id' => $this->business->district_id,
                'phone' => $this->business->phone,
                'whatsapp_phone' => $this->business->whatsapp_phone,
                'latitude' => $this->business->latitude,
                'longitude' => $this->business->longitude,
                'photo_thumbnail_url' => $this->business->photos->first()?->thumbnailUrl(),
            ]),
            'client' => $this->whenLoaded('client', fn () => [
                'name' => $this->client->name,
                'phone' => $this->client->phone,
            ]),
        ];
    }
}
