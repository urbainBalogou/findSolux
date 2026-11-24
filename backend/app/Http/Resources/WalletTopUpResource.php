<?php

namespace App\Http\Resources;

use App\Enums\WalletTopUpStatus;
use App\Models\WalletTopUp;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WalletTopUp */
class WalletTopUpResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'amount' => ['value' => $this->amount_xof, 'currency' => 'XOF'],
            'status' => $this->status->value,
            'payment_method' => [
                'code' => $this->payment_method->value,
                'label' => $this->payment_method->label(),
            ],
            'requires_action' => $this->status === WalletTopUpStatus::Pending,
            'message' => match ($this->status) {
                WalletTopUpStatus::Pending => 'Validez la demande sur votre téléphone.',
                WalletTopUpStatus::Succeeded => 'Le portefeuille a été crédité.',
                WalletTopUpStatus::Failed => $this->failure_reason ?: 'La recharge a échoué.',
                WalletTopUpStatus::Cancelled => 'La recharge a été annulée.',
                WalletTopUpStatus::Refunded => 'La recharge a été remboursée.',
            },
            'created_at' => $this->created_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
        ];
    }
}
