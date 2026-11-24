<?php

namespace App\Http\Resources;

use App\Enums\WalletPaymentMethod;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Wallet */
class WalletResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'balance' => ['amount' => $this->balance_xof, 'currency' => 'XOF'],
            'payment_methods' => array_map(
                fn (WalletPaymentMethod $method): array => [
                    'code' => $method->value,
                    'label' => $method->label(),
                ],
                WalletPaymentMethod::cases(),
            ),
            'top_up_limits' => [
                'minimum' => (int) config('services.fedapay.minimum_top_up_xof', 100),
                'maximum' => (int) config('services.fedapay.maximum_top_up_xof', 1_000_000),
                'currency' => 'XOF',
            ],
            'transactions' => WalletEntryResource::collection($this->whenLoaded('entries')),
            'top_ups' => WalletTopUpResource::collection($this->whenLoaded('topUps')),
        ];
    }
}
