<?php

namespace App\Enums;

enum GalleryType: string
{
    case Photo = 'photo';
    case Video = 'video';

    public function label(): string
    {
        return match ($this) {
            self::Photo => __('Photo album'),
            self::Video => __('Video'),
        };
    }
}
