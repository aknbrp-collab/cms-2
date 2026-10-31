<?php

namespace App\Enums;

enum LegalCategory: string
{
    case Ibadah = 'ibadah';
    case Muamalah = 'muamalah';
    case Munakahat = 'munakahat';
    case Mawaris = 'mawaris';
    case Jinayah = 'jinayah';
    case Akhlak = 'akhlak';
    case Other = 'lainnya';

    public function label(): string
    {
        return match ($this) {
            self::Ibadah => 'Ibadah',
            self::Muamalah => "Mu'amalah",
            self::Munakahat => 'Munakahat (Pernikahan)',
            self::Mawaris => 'Mawaris (Waris)',
            self::Jinayah => 'Jinayah',
            self::Akhlak => 'Akhlak & Tasawuf',
            self::Other => 'Lainnya',
        };
    }
}
