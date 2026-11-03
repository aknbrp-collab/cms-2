<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case NeedsFix = 'needs_fix';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Waiting for review'),
            self::Accepted => __('Accepted'),
            self::NeedsFix => __('Needs fixing'),
        };
    }

    /**
     * Label on the public site, which is written in Indonesian.
     */
    public function publicLabel(): string
    {
        return match ($this) {
            self::Pending => __('Menunggu pemeriksaan'),
            self::Accepted => __('Diterima'),
            self::NeedsFix => __('Perlu perbaikan'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'sky',
            self::Accepted => 'emerald',
            self::NeedsFix => 'amber',
        };
    }
}
