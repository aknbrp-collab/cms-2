<?php

namespace App\Support;

/**
 * Turn a shared video or map link into a safe iframe URL.
 *
 * Only YouTube and Google Maps are allowed, so editors can never inject
 * arbitrary iframes.
 */
class Embed
{
    public static function toEmbedUrl(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $url = trim($url);

        if (preg_match('~^https?://(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $matches)) {
            return "https://www.youtube-nocookie.com/embed/{$matches[1]}";
        }

        if (preg_match('~^https://(?:www\.)?google\.com/maps/embed\?pb=[^\s"\'<>]+$~', $url)) {
            return $url;
        }

        return null;
    }

    public static function isAllowed(?string $url): bool
    {
        return static::toEmbedUrl($url) !== null;
    }
}
