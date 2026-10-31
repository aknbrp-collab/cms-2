<?php

namespace App\Enums;

enum MenuLocation: string
{
    case Header = 'header';
    case TopBar = 'top_bar';
    case Footer = 'footer';

    public function label(): string
    {
        return match ($this) {
            self::Header => __('Main navigation'),
            self::TopBar => __('Top bar links'),
            self::Footer => __('Footer links'),
        };
    }
}
