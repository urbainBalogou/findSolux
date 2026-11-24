<?php

namespace App\Services\Requests;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Signal de détresse ou demande de contact refusé : déjà pris en charge,
 * expiré, aucun garage à proximité, adhésion inactive…
 */
class RequestNotAvailableException extends RuntimeException
{
    public function __construct(string $message, public readonly string $reason, public readonly int $status = 409)
    {
        parent::__construct($message);
    }

    /**
     * Un garage sans adhésion active ne peut pas accepter de demande (§4.2).
     */
    public static function membershipRequired(): self
    {
        return new self(
            'Votre adhésion n’est pas active : renouvelez-la pour accepter des demandes.',
            'membership_required',
            403,
        );
    }

    /**
     * Le garage qui accepte paie pour joindre le client (ADR-017) : sans
     * numéro, la demande ne lui servirait à rien.
     */
    public static function phoneRequired(): self
    {
        return new self(
            'Ajoutez le numéro où vous joindre avant d’envoyer une demande : le garage vous rappellera à ce numéro.',
            'phone_required',
            422,
        );
    }

    public static function alreadyAnswered(): self
    {
        return new self('Cette demande a déjà reçu une réponse ou n’est plus active.', 'not_available');
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'code' => $this->reason], $this->status);
    }
}
