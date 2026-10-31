<?php

namespace App\Enums;

enum PostType: string
{
    case News = 'news';
    case Announcement = 'announcement';
    case Article = 'article';

    public function label(): string
    {
        return match ($this) {
            self::News => __('News'),
            self::Announcement => __('Announcement'),
            self::Article => __('Article'),
        };
    }
}
