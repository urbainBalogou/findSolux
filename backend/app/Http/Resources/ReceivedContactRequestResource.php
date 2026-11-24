<?php

namespace App\Http\Resources;

use App\Enums\ContactRequestStatus;
use App\Models\ContactRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Demande de contact vue par le garage. Le numéro du client n'est donné
 * qu'après acceptation.
 *
 * @mixin ContactRequest
 */
class ReceivedContactRequestResource extends JsonResource
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
            'client' => [
                'name' => $this->client->name,
                'phone' => $isAccepted ? $this->client->phone : null,
            ],
            // Garagiste ou membre de son équipe qui a accepté.
            'accepted_by' => $isAccepted && $this->acceptedBy !== null
                ? ['id' => $this->acceptedBy->id, 'name' => $this->acceptedBy->name]
                : null,
        ];
    }
}
