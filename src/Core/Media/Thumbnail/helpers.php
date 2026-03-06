<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

use Core\Media\Thumbnail\LazyThumbnail;

if (! function_exists('lazy_thumb')) {
    /**
     * Get a lazy thumbnail URL for an image.
     *
     * Usage in Blade:
     * ```blade
     * <img src="{{ lazy_thumb('uploads/photo.jpg', 200, 200) }}" alt="Photo">
     * ```
     *
     * @param  string  $sourcePath  Path to the source image
     * @param  int  $width  Target width in pixels
     * @param  int  $height  Target height in pixels
     * @return string The thumbnail URL
     */
    function lazy_thumb(string $sourcePath, int $width, int $height): string
    {
        return app(LazyThumbnail::class)->url($sourcePath, $width, $height);
    }
}

if (! function_exists('lazy_thumb_exists')) {
    /**
     * Check if a lazy thumbnail has been generated.
     *
     * @param  string  $sourcePath  Path to the source image
     * @param  int  $width  Target width in pixels
     * @param  int  $height  Target height in pixels
     * @return bool True if thumbnail exists
     */
    function lazy_thumb_exists(string $sourcePath, int $width, int $height): bool
    {
        return app(LazyThumbnail::class)->exists($sourcePath, $width, $height);
    }
}
