<?php

namespace App\Support;

use Carbon\CarbonInterface;
use IntlDateFormatter;

/**
 * Format a date in the Hijri (Umm al-Qura) calendar with the month names
 * commonly used in pesantren, e.g. "14 Syawwal 1445 H".
 */
class HijriDate
{
    public const MONTHS = [
        1 => 'Muharram', 'Shafar', "Rabi'ul Awwal", "Rabi'ul Akhir", 'Jumadil Ula', 'Jumadil Akhirah',
        'Rajab', "Sya'ban", 'Ramadhan', 'Syawwal', "Dzulqa'dah", 'Dzulhijjah',
    ];

    public static function format(CarbonInterface $date): string
    {
        $formatter = new IntlDateFormatter(
            'en_US@calendar=islamic-umalqura',
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            $date->getTimezone(),
            IntlDateFormatter::TRADITIONAL,
            'd/M/y',
        );

        [$day, $month, $year] = array_map('intval', explode('/', (string) $formatter->format($date->getTimestamp())));

        return sprintf('%d %s %d H', $day, self::MONTHS[$month] ?? '', $year);
    }
}
