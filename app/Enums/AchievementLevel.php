<?php

namespace App\Enums;

/**
 * Level of the competition an achievement was won at.
 */
enum AchievementLevel: string
{
    case School = 'school';
    case District = 'district';
    case Province = 'province';
    case National = 'national';
    case International = 'international';

    public function label(): string
    {
        return match ($this) {
            self::School => __('Internal'),
            self::District => __('Kabupaten/Kota'),
            self::Province => __('Provinsi'),
            self::National => __('Nasional'),
            self::International => __('Internasional'),
        };
    }
}
