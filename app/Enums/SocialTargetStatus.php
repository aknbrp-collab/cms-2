<?php

namespace App\Enums;

enum SocialTargetStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Published = 'published';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Waiting'),
            self::Queued => __('Publishing'),
            self::Published => __('Published'),
            self::Failed => __('Failed'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'zinc',
            self::Queued => 'amber',
            self::Published => 'emerald',
            self::Failed => 'red',
        };
    }
}
