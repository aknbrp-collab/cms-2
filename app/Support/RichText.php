<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Sanitise HTML coming from the rich text editor before it is stored.
 */
class RichText
{
    protected static ?HtmlSanitizer $sanitizer = null;

    public static function clean(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        return static::sanitizer()->sanitize($html);
    }

    protected static function sanitizer(): HtmlSanitizer
    {
        return static::$sanitizer ??= new HtmlSanitizer(
            (new HtmlSanitizerConfig)
                ->allowSafeElements()
                ->allowLinkSchemes(['https', 'http', 'mailto', 'tel'])
                ->allowRelativeLinks()
                ->allowMediaSchemes(['https'])
                ->allowRelativeMedias()
                ->forceAttribute('a', 'rel', 'noopener noreferrer')
                ->withMaxInputLength(500_000),
        );
    }
}
