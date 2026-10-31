<?php

namespace App\Enums;

/**
 * Content blocks available in the page builder.
 */
enum BlockType: string
{
    case RichText = 'rich_text';
    case Image = 'image';
    case CallToAction = 'call_to_action';
    case Embed = 'embed';

    public function label(): string
    {
        return match ($this) {
            self::RichText => __('Text'),
            self::Image => __('Image'),
            self::CallToAction => __('Call to action'),
            self::Embed => __('Video / map embed'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::RichText => 'document-text',
            self::Image => 'photo',
            self::CallToAction => 'megaphone',
            self::Embed => 'play-circle',
        };
    }

    /**
     * Empty data for a new block of this type.
     *
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return match ($this) {
            self::RichText => ['html' => ''],
            self::Image => ['media_asset_id' => null, 'caption' => ''],
            self::CallToAction => ['title' => '', 'text' => '', 'button_label' => '', 'button_url' => ''],
            self::Embed => ['url' => '', 'caption' => ''],
        };
    }
}
