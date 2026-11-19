<?php

namespace App\Support;

use Spatie\Image\Enums\ImageDriver;
use Spatie\Image\Image;

/**
 * Re-encode an image in place: applies the EXIF rotation, then drops all
 * metadata (GPS location, camera) and anything hidden after the image data.
 *
 * Always uses GD. spatie/image otherwise picks Imagick when that extension
 * is installed, and Imagick keeps EXIF data when saving.
 */
class ImageReencoder
{
    public static function reencode(string $path): void
    {
        Image::useImageDriver(ImageDriver::Gd)->loadFile($path)->orientation()->save($path);
    }
}
