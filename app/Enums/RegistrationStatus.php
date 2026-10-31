<?php

namespace App\Enums;

enum RegistrationStatus: string
{
    case New = 'new';
    case Verified = 'verified';
    case Accepted = 'accepted';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::New => __('New'),
            self::Verified => __('Documents verified'),
            self::Accepted => __('Accepted'),
            self::Rejected => __('Not accepted'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'sky',
            self::Verified => 'amber',
            self::Accepted => 'emerald',
            self::Rejected => 'red',
        };
    }
}
