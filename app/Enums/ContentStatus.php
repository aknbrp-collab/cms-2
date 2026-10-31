<?php

namespace App\Enums;

enum ContentStatus: string
{
    case Draft = 'draft';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Published = 'published';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::UnderReview => __('Under review'),
            self::Approved => __('Approved'),
            self::Published => __('Published'),
            self::Archived => __('Archived'),
        };
    }

    /**
     * Flux badge color for this status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Draft => 'zinc',
            self::UnderReview => 'amber',
            self::Approved => 'sky',
            self::Published => 'emerald',
            self::Archived => 'red',
        };
    }
}
