<?php

namespace App\Http\Resources;

use App\Enums\DistressSignalStatus;
use App\Models\DistressSignal;
use App\Models\DistressSignalRecipient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Signal de détresse vu par un garage alerté. La relation `recipients` ne
 * doit contenir que la ligne de ce garage. La position exacte et le numéro du
 * client ne sont donnés qu'au garage qui a accepté.
 *
 * @mixin DistressSignal
 */
class ReceivedDistressSignalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var DistressSignalRecipient $recipient */
        $recipient = $this->recipients->first();
        $isAcceptedByMe = $this->status === DistressSignalStatus::Accepted
            && $this->accepted_business_id === $recipient->business_id;

        return [
            'id' => $this->public_id,
            'status' => $this->statusFor($recipient, $isAcceptedByMe),
            'message' => $this->message,
            'service_id' => $this->service_id,
            'vehicle_type_id' => $this->vehicle_type_id,
            'distance_m' => $recipient->distance_m,
            'expires_at' => $this->expires_at->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'client' => $isAcceptedByMe ? [
                'name' => $this->client->name,
                'phone' => $this->client->phone,
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
            ] : null,
            // Garagiste ou membre de son équipe qui a accepté.
            'accepted_by' => $isAcceptedByMe && $this->acceptedBy !== null
                ? ['id' => $this->acceptedBy->id, 'name' => $this->acceptedBy->name]
                : null,
        ];
    }

    /**
     * @return 'open'|'declined'|'accepted'|'taken'|'expired'|'cancelled'
     */
    private function statusFor(DistressSignalRecipient $recipient, bool $isAcceptedByMe): string
    {
        return match (true) {
            $isAcceptedByMe => 'accepted',
            $this->status === DistressSignalStatus::Accepted => 'taken',
            $this->status === DistressSignalStatus::Cancelled => 'cancelled',
            ! $this->isOpen() => 'expired',
            $recipient->declined_at !== null => 'declined',
            default => 'open',
        };
    }
}
