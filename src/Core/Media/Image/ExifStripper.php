<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Media\Image;

use Illuminate\Support\Facades\Log;

/**
 * EXIF data stripper for privacy protection.
 *
 * Removes EXIF metadata from images to protect user privacy.
 * EXIF data can contain sensitive information like GPS coordinates,
 * camera details, and timestamps.
 *
 * Supports both GD and Imagick drivers.
 */
class ExifStripper
{
    protected string $driver;

    protected bool $enabled;

    public function __construct()
    {
        $this->enabled = config('media.exif.strip_exif', true);
        $this->driver = config('media.optimization.driver', 'gd');

        // Validate driver availability
        if ($this->driver === 'imagick' && ! extension_loaded('imagick')) {
            Log::debug('ExifStripper: imagick driver not available, falling back to gd');
            $this->driver = 'gd';
        }
    }

    /**
     * Check if EXIF stripping is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Strip EXIF data from an image file.
     *
     * @param  string  $path  Absolute path to the image file
     * @return bool True if EXIF was stripped (or not present), false on error
     */
    public function strip(string $path): bool
    {
        if (! $this->enabled) {
            return true;
        }

        if (! file_exists($path)) {
            Log::warning('ExifStripper: File not found', ['path' => $path]);

            return false;
        }

        $imageInfo = @getimagesize($path);

        if (! $imageInfo) {
            Log::warning('ExifStripper: Not a valid image', ['path' => $path]);

            return false;
        }

        $mimeType = $imageInfo['mime'];

        // Only JPEG and TIFF files typically contain EXIF data
        if (! in_array($mimeType, ['image/jpeg', 'image/jpg', 'image/tiff'])) {
            return true;
        }

        try {
            if ($this->driver === 'imagick' && extension_loaded('imagick')) {
                return $this->stripWithImagick($path);
            }

            return $this->stripWithGd($path);
        } catch (\Throwable $e) {
            Log::error('ExifStripper: Failed to strip EXIF data', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Strip EXIF data using GD library.
     *
     * GD doesn't preserve EXIF when re-saving, so we simply
     * load and re-save the image to strip the metadata.
     */
    protected function stripWithGd(string $path): bool
    {
        $imageInfo = @getimagesize($path);

        if (! $imageInfo) {
            return false;
        }

        $type = $imageInfo[2];

        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            default => null,
        };

        if ($image === null || $image === false) {
            return false;
        }

        // Get current quality from config
        $quality = config('media.optimization.quality', 80);

        // Re-save without EXIF data
        $success = imagejpeg($image, $path, $quality);
        imagedestroy($image);

        if ($success) {
            Log::debug('ExifStripper: Stripped EXIF data using GD', ['path' => $path]);
        }

        return $success;
    }

    /**
     * Strip EXIF data using Imagick.
     *
     * Imagick provides more granular control over metadata removal.
     */
    protected function stripWithImagick(string $path): bool
    {
        $imagick = new \Imagick($path);

        // Strip all profiles and comments
        $imagick->stripImage();

        // Write back to file
        $success = $imagick->writeImage($path);
        $imagick->destroy();

        if ($success) {
            Log::debug('ExifStripper: Stripped EXIF data using Imagick', ['path' => $path]);
        }

        return $success;
    }

    /**
     * Strip EXIF data from image content (in memory).
     *
     * @param  string  $content  Raw image content
     * @return string|null Stripped image content or null on error
     */
    public function stripFromContent(string $content): ?string
    {
        if (! $this->enabled) {
            return $content;
        }

        $imageInfo = @getimagesizefromstring($content);

        if (! $imageInfo) {
            return null;
        }

        $mimeType = $imageInfo['mime'];

        // Only process JPEG images
        if (! in_array($mimeType, ['image/jpeg', 'image/jpg'])) {
            return $content;
        }

        try {
            if ($this->driver === 'imagick' && extension_loaded('imagick')) {
                return $this->stripContentWithImagick($content);
            }

            return $this->stripContentWithGd($content);
        } catch (\Throwable $e) {
            Log::error('ExifStripper: Failed to strip EXIF from content', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Strip EXIF from content using GD.
     */
    protected function stripContentWithGd(string $content): ?string
    {
        $image = @imagecreatefromstring($content);

        if ($image === false) {
            return null;
        }

        $quality = config('media.optimization.quality', 80);

        ob_start();
        $success = imagejpeg($image, null, $quality);
        $result = ob_get_clean();
        imagedestroy($image);

        if (! $success || $result === false || $result === '') {
            return null;
        }

        return $result;
    }

    /**
     * Strip EXIF from content using Imagick.
     */
    protected function stripContentWithImagick(string $content): ?string
    {
        $imagick = new \Imagick;
        $imagick->readImageBlob($content);
        $imagick->stripImage();

        $result = $imagick->getImageBlob();
        $imagick->destroy();

        return $result;
    }

    /**
     * Check if an image has EXIF data.
     *
     * @param  string  $path  Absolute path to the image file
     * @return bool True if EXIF data is present
     */
    public function hasExifData(string $path): bool
    {
        if (! function_exists('exif_read_data')) {
            return false;
        }

        try {
            $exif = @exif_read_data($path);

            return is_array($exif) && count($exif) > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Get EXIF data from an image (for display before stripping).
     *
     * @param  string  $path  Absolute path to the image file
     * @return array<string, mixed> EXIF data or empty array
     */
    public function getExifData(string $path): array
    {
        if (! function_exists('exif_read_data')) {
            return [];
        }

        try {
            $exif = @exif_read_data($path, 'ANY_TAG', true);

            return is_array($exif) ? $exif : [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}
