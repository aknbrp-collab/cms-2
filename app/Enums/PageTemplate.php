<?php

namespace App\Enums;

enum PageTemplate: string
{
    case Default = 'default';
    case FullWidth = 'full_width';
    case Contact = 'contact';

    public function label(): string
    {
        return match ($this) {
            self::Default => __('Standard page'),
            self::FullWidth => __('Full width'),
            self::Contact => __('Contact page'),
        };
    }
}
