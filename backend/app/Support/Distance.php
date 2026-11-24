<?php

namespace App\Support;

final class Distance
{
    /**
     * Distance lisible, comme dans l'application : « 350 m », « 2,4 km », « 12 km ».
     */
    public static function format(int $meters): string
    {
        return match (true) {
            $meters < 1000 => (int) (round($meters / 10) * 10).' m',
            $meters < 10000 => number_format($meters / 1000, 1, ',', '').' km',
            default => (int) round($meters / 1000).' km',
        };
    }
}
