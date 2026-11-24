<?php

namespace App\Support;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberType;
use libphonenumber\PhoneNumberUtil;

final class PhoneNumber
{
    /**
     * Numéro du pays donné au format E.164 (+228XXXXXXXX), ou null s'il est
     * invalide. Accepte les saisies usuelles : « 90 12 34 56 »,
     * « +228 90123456 », « 0022890123456 ».
     */
    public static function normalize(string $input, string $region, bool $mobileOnly = false): ?string
    {
        $util = PhoneNumberUtil::getInstance();

        try {
            $number = $util->parse($input, $region);
        } catch (NumberParseException) {
            return null;
        }

        if (! $util->isValidNumberForRegion($number, $region)) {
            return null;
        }

        if ($mobileOnly && $util->getNumberType($number) !== PhoneNumberType::MOBILE) {
            return null;
        }

        return $util->format($number, PhoneNumberFormat::E164);
    }

    /**
     * Affichage lisible d'un numéro E.164 : « +228 90 12 34 56 ».
     */
    public static function display(string $e164): string
    {
        if (preg_match('/^\+228(\d{8})$/', $e164, $match) !== 1) {
            return $e164;
        }

        return '+228 '.implode(' ', str_split($match[1], 2));
    }
}
