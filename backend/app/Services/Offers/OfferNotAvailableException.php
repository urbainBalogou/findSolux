<?php

namespace App\Services\Offers;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Achat ou réglage d'offre refusé (offre retirée, non achetable, adhésion déjà illimitée…).
 */
class OfferNotAvailableException extends RuntimeException
{
    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'code' => 'offer_not_available'], 422);
    }
}
