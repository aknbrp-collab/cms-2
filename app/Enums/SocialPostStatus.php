<?php

namespace App\Enums;

enum SocialPostStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Publishing = 'publishing';
    case Published = 'published';
    case PartiallyFailed = 'partially_failed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Scheduled => __('Scheduled'),
            self::Publishing => __('Publishing'),
            self::Published => __('Published'),
            self::PartiallyFailed => __('Partly failed'),
            self::Failed => __('Failed'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'zinc',
            self::Scheduled => 'sky',
            self::Publishing => 'amber',
            self::Published => 'emerald',
            self::PartiallyFailed => 'orange',
            self::Failed => 'red',
        };
    }

    /**
     * Posts in these states can still be edited and rescheduled.
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Scheduled], true);
    }
}
