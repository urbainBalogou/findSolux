<?php

namespace App\Services\Auth;

/**
 * Compte Google dont le jeton d'identité a été vérifié.
 */
final readonly class GoogleIdentity
{
    public function __construct(
        public string $subject,
        public string $email,
        public ?string $name,
    ) {}
}
