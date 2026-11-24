<?php

namespace App\Http\Resources;

use App\Enums\SubscriptionStatus;
use App\Models\BusinessSubscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Offre achetée ou accordée, vue par le garagiste.
 *
 * @mixin BusinessSubscription
 */
class BusinessSubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isActive = $this->status === SubscriptionStatus::Active;

        return [
            'id' => $this->id,
            'offer_id' => $this->commercial_offer_id,
            'type' => $this->offer_type->value,
            'name' => $this->name,
            'quantity' => $this->quantity,
            'price' => ['amount' => $this->price_xof, 'currency' => 'XOF'],
            'duration' => [
                'type' => $this->is_unlimited ? 'unlimited' : 'limited',
                'value' => $this->is_unlimited ? null : $this->duration_value,
                'unit' => $this->is_unlimited ? null : $this->duration_unit?->value,
            ],
            'starts_at' => $this->starts_at->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'status' => $this->status->value,
            // En cours maintenant ; une prolongation programmée est active mais pas encore en cours.
            'is_current' => $this->isCurrent(),
            'source' => $this->source->value,
            'auto_renew' => $this->auto_renew,
            'can_auto_renew' => $isActive && ! $this->is_unlimited,
        ];
    }
}
