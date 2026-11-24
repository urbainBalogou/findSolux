<?php

namespace App\Services\Push;

/**
 * Notification push : titre et texte affichés, données lues par l'application
 * pour ouvrir le bon écran (`type`, identifiants…).
 */
final readonly class PushMessage
{
    /**
     * @param  array<string, string>  $data
     */
    public function __construct(
        public string $title,
        public string $body,
        public array $data = [],
    ) {}
}
