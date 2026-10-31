<?php

namespace App\Support;

use enshrined\svgSanitize\Sanitizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Strip scripts, event handlers and external references from uploaded SVGs.
 *
 * Uploads are served from the CMS's own domains, so a scripted SVG opened
 * directly would run with the viewer's admin session.
 */
class SvgSanitizer
{
    /**
     * Sanitize the uploaded file in place when it is an SVG.
     *
     * @throws ValidationException when the SVG cannot be parsed
     */
    public static function clean(UploadedFile $file, string $errorKey = 'upload'): void
    {
        if (! static::isSvg($file)) {
            return;
        }

        $path = (string) $file->getRealPath();
        $clean = static::sanitize((string) file_get_contents($path));

        if ($clean === null) {
            throw ValidationException::withMessages([$errorKey => __('The SVG file :name is invalid.', ['name' => $file->getClientOriginalName()])]);
        }

        file_put_contents($path, $clean);
    }

    /**
     * Sanitized markup, or null when it is not a valid SVG document.
     */
    public static function sanitize(string $svg): ?string
    {
        $sanitizer = new Sanitizer;
        $sanitizer->removeRemoteReferences(true);
        $clean = $sanitizer->sanitize($svg);

        return is_string($clean) && $clean !== '' ? $clean : null;
    }

    protected static function isSvg(UploadedFile $file): bool
    {
        return strtolower($file->getClientOriginalExtension()) === 'svg'
            || in_array($file->getMimeType(), ['image/svg+xml', 'image/svg'], true);
    }
}
