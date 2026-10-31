<?php

namespace App\Enums;

/**
 * Review status of an article received from SIAKAD.
 */
enum SiakadArticleStatus: string
{
    case Draft = 'draft';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::UnderReview => __('Under review'),
            self::Approved => __('Approved for website'),
            self::Rejected => __('Rejected'),
            self::Withdrawn => __('Withdrawn by SIAKAD'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'zinc',
            self::UnderReview => 'amber',
            self::Approved => 'emerald',
            self::Rejected => 'red',
            self::Withdrawn => 'zinc',
        };
    }
}
