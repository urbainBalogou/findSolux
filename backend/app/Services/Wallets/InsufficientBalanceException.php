<?php

namespace App\Services\Wallets;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Débit refusé : le portefeuille n'a pas de découvert (ADR-014).
 */
class InsufficientBalanceException extends RuntimeException
{
    public function __construct(public readonly int $requiredXof, public readonly int $balanceXof)
    {
        parent::__construct('Solde insuffisant. Rechargez votre portefeuille pour continuer.');
    }

    public function missingXof(): int
    {
        return max(0, $this->requiredXof - $this->balanceXof);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => 'insufficient_balance',
            'required' => ['amount' => $this->requiredXof, 'currency' => 'XOF'],
            'balance' => ['amount' => $this->balanceXof, 'currency' => 'XOF'],
            'missing' => ['amount' => $this->missingXof(), 'currency' => 'XOF'],
        ], 422);
    }
}
