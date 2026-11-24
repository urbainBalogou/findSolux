<?php

namespace App\Http\Resources;

use App\Models\WalletEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WalletEntry */
class WalletEntryResource extends JsonResource
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
            'direction' => $this->direction->value,
            'amount' => ['value' => $this->amount_xof, 'currency' => 'XOF'],
            'balance_after' => [
                'value' => $this->balance_after_xof,
                'currency' => 'XOF',
            ],
            'description' => $this->description,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
