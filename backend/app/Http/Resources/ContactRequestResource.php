<?php

namespace App\Http\Resources;

use App\Enums\ContactRequestStatus;
use App\Models\ContactRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Demande de contact vue par le client. Une fois acceptée, elle contient le
 * numéro du garage.
 *
 * @mixin ContactRequest
 */
class ContactRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isAccepted = $this->status === ContactRequestStatus::Accepted;

        return [
            'id' => $this->public_id,
            'status' => $this->status === ContactRequestStatus::Pending && ! $this->isPending()
                ? ContactRequestStatus::Expired->value
                : $this->status->value,
            'message' => $this->message,
            'expires_at' => $this->expires_at->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'responded_at' => $this->responded_at?->toIso8601String(),
            'garage' => [
                'slug' => $this->business->slug,
                'name' => $this->business->name,
                'district_id' => $this->business->district_id,
                'phone' => $isAccepted ? $this->business->phone : null,
                'whatsapp_phone' => $isAccepted ? $this->business->whatsapp_phone : null,
            ],
        ];
    }
}
