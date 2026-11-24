<?php

namespace App\Http\Resources;

use App\Enums\DistressSignalStatus;
use App\Models\DistressSignal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Signal de détresse vu par le client qui l'a envoyé. Une fois accepté, il
 * contient les coordonnées du garage.
 *
 * @mixin DistressSignal
 */
class DistressSignalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $business = $this->status === DistressSignalStatus::Accepted ? $this->acceptedBusiness : null;

        return [
            'id' => $this->public_id,
            'status' => $this->clientStatus(),
            'message' => $this->message,
            'service_id' => $this->service_id,
            'vehicle_type_id' => $this->vehicle_type_id,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'recipients_count' => $this->whenCounted('recipients'),
            'expires_at' => $this->expires_at->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'garage' => $business === null ? null : [
                'slug' => $business->slug,
                'name' => $business->name,
                'district_id' => $business->district_id,
                'phone' => $business->phone,
                'whatsapp_phone' => $business->whatsapp_phone,
                'latitude' => $business->latitude,
                'longitude' => $business->longitude,
            ],
        ];
    }

    /**
     * « expired » dès la fin du délai, sans attendre la tâche planifiée.
     */
    private function clientStatus(): string
    {
        if ($this->status === DistressSignalStatus::Open && ! $this->isOpen()) {
            return DistressSignalStatus::Expired->value;
        }

        return $this->status->value;
    }
}
