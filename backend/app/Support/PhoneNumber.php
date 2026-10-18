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
}
