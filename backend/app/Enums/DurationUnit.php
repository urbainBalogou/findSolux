<?php

namespace App\Enums;

use Carbon\CarbonImmutable;

enum DurationUnit: string
{
    case Day = 'day';
    case Month = 'month';
    case Year = 'year';

    public function label(int $value): string
    {
        return match ($this) {
            self::Day => $value > 1 ? 'jours' : 'jour',
            self::Month => 'mois',
            self::Year => $value > 1 ? 'ans' : 'an',
        };
    }

    public function addTo(CarbonImmutable $startsAt, int $value): CarbonImmutable
    {
        return match ($this) {
            self::Day => $startsAt->addDays($value),
            self::Month => $startsAt->addMonthsNoOverflow($value),
            self::Year => $startsAt->addYearsNoOverflow($value),
        };
    }
}
