<?php

namespace App\Services\Auth;

use Illuminate\Http\JsonResponse;
use RuntimeException;
use Throwable;

/**
 * Connexion Google refusée (jeton invalide ou expiré) ou impossible pour le
 * moment (serveur non configuré, certificats de Google injoignables).
 */
class GoogleSignInException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 401, ?Throwable $previous = null)
    {
        parent::__construct($message, previous: $previous);
    }

    public static function invalidToken(): self
    {
        return new self('La connexion Google a échoué. Réessayez.');
    }

    public static function unavailable(?Throwable $previous = null): self
    {
        return new self('La connexion Google est momentanément indisponible. Réessayez dans quelques instants.', 503, $previous);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], $this->status);
    }
}
