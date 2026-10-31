<?php

namespace App\Enums;

enum MenuItemType: string
{
    case Url = 'url';
    case Page = 'page';
    case Category = 'category';

    public function label(): string
    {
        return match ($this) {
            self::Url => __('Custom link'),
            self::Page => __('Page'),
            self::Category => __('Post category'),
        };
    }
}
