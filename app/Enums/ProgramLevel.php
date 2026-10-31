<?php

namespace App\Enums;

/**
 * Ma'had Aly study level (marhalah).
 */
enum ProgramLevel: string
{
    case Ula = 'ula';
    case Tsaniyah = 'tsaniyah';
    case Tsalitsah = 'tsalitsah';

    public function label(): string
    {
        return match ($this) {
            self::Ula => __('Marhalah Ula (S1)'),
            self::Tsaniyah => __('Marhalah Tsaniyah (S2)'),
            self::Tsalitsah => __('Marhalah Tsalitsah (S3)'),
        };
    }
}
