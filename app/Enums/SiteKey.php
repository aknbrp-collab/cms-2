<?php

namespace App\Enums;

enum SiteKey: string
{
    case Pondok = 'pondok';
    case Mahad = 'mahad';

    public function label(): string
    {
        return match ($this) {
            self::Pondok => 'Pondok Pesantren',
            self::Mahad => "Ma'had Aly",
        };
    }
}
