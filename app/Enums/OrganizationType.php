<?php

namespace App\Enums;

/**
 * Kind of student organization.
 */
enum OrganizationType: string
{
    case Dema = 'dema';
    case Ukm = 'ukm';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Dema => __('Dewan Mahasantri (DEMA)'),
            self::Ukm => __('Unit Kegiatan Mahasantri (UKM)'),
            self::Other => __('Lainnya'),
        };
    }
}
