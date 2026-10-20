<?php

namespace App\Support;

final class Price
{
    /**
     * Fourchette indicative en FCFA, comme dans l'application :
     * « 5 000 – 15 000 FCFA », « À partir de 5 000 FCFA », « Prix sur demande ».
     */
    public static function range(?int $min, ?int $max): string
    {
        $amount = fn (int $value) => number_format($value, 0, ',', "\u{202F}");

        return match (true) {
            $min !== null && $max !== null && $min === $max => $amount($min).' FCFA',
            $min !== null && $max !== null => $amount($min).' – '.$amount($max).' FCFA',
            $min !== null => 'À partir de '.$amount($min).' FCFA',
            $max !== null => 'Jusqu’à '.$amount($max).' FCFA',
            default => 'Prix sur demande',
        };
    }
}
