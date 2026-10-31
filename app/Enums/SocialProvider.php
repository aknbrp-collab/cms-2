<?php

namespace App\Enums;

enum SocialProvider: string
{
    case Facebook = 'facebook';
    case Instagram = 'instagram';
    case TikTok = 'tiktok';

    public function label(): string
    {
        return match ($this) {
            self::Facebook => 'Facebook',
            self::Instagram => 'Instagram',
            self::TikTok => 'TikTok',
        };
    }

    /**
     * The OAuth app that connects this provider (Instagram connects through Meta).
     */
    public function oauthApp(): string
    {
        return $this === self::TikTok ? 'tiktok' : 'meta';
    }

    public function captionLimit(): int
    {
        return (int) config("social.limits.{$this->value}.caption");
    }

    public function hashtagLimit(): ?int
    {
        $limit = config("social.limits.{$this->value}.hashtags");

        return $limit === null ? null : (int) $limit;
    }

    /**
     * Whether a post needs at least one image or video.
     */
    public function requiresMedia(): bool
    {
        return $this !== self::Facebook;
    }
}
