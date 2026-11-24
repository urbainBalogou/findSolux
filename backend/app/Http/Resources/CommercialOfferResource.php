<?php

namespace App\Http\Resources;

use App\Models\CommercialOffer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CommercialOffer */
class CommercialOfferResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'name' => $this->name,
            'is_purchasable' => $this->type->isPurchasable(),
            'quantity' => $this->quantity,
            'price' => [
                'amount' => $this->price_xof,
                'currency' => 'XOF',
            ],
            'duration' => [
                'type' => $this->is_unlimited ? 'unlimited' : 'limited',
                'value' => $this->is_unlimited ? null : $this->duration_value,
                'unit' => $this->is_unlimited ? null : $this->duration_unit?->value,
            ],
            'position' => $this->position,
        ];
    }
}
