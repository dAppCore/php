<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Media\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Image resizing utility for media processing.
 *
 * Resizes images while maintaining aspect ratio and preventing upscaling.
 * Includes memory safety checks to prevent out-of-memory crashes.
 */
class ImageResizer
{
    protected string $content;

    protected ?string $sourcePath = null;

    protected ?string $disk = null;

    protected ?string $path = null;

    /**
     * Default memory safety factor.
     * GD typically needs 5-6x the image size in memory.
     */
    protected const MEMORY_SAFETY_FACTOR = 6;

    /**
     * Minimum memory buffer to maintain (in bytes).
     */
    protected const MEMORY_BUFFER = 32 * 1024 * 1024; // 32MB

    public function __construct(string $contentOrPath)
    {
        if (file_exists($contentOrPath)) {
            $this->sourcePath = $contentOrPath;
            $this->content = file_get_contents($contentOrPath);
        } else {
            $this->content = $contentOrPath;
        }
    }

    /**
     * Create a new ImageResizer instance.
     */
    public static function make(string $contentOrPath): static
    {
        return new static($contentOrPath);
    }

    /**
     * Set the destination disk.
     */
    public function disk(string $disk): static
    {
        $this->disk = $disk;

        return $this;
    }

    /**
     * Set the destination path.
     */
    public function path(string $path): static
    {
        $this->path = $path;

        return $this;
    }

    /**
     * Resize the image to fit within the specified dimensions.
     *
     * Maintains aspect ratio and prevents upscaling.
     *
     * @throws RuntimeException If memory is insufficient or image processing fails
     */
    public function resize(int $maxWidth, int $maxHeight): bool
    {
        if ($this->disk === null || $this->path === null) {
            throw new RuntimeException('Disk and path must be set before resizing');
        }

        // Get image info for memory estimation
        $imageInfo = $this->getImageInfo();

        if ($imageInfo === null) {
            Log::warning('ImageResizer: Failed to get image info, cannot resize');

            return $this->saveOriginal();
        }

        // Check memory before processing
        if (! $this->hasEnoughMemory($imageInfo['width'], $imageInfo['height'])) {
            Log::warning('ImageResizer: Insufficient memory for image processing', [
                'width' => $imageInfo['width'],
                'height' => $imageInfo['height'],
                'estimated_memory' => $this->estimateRequiredMemory($imageInfo['width'], $imageInfo['height']),
                'available_memory' => $this->getAvailableMemory(),
            ]);

            return $this->saveOriginal();
        }

        $image = $this->createImageFromContent();

        if ($image === null) {
            return $this->saveOriginal();
        }

        $originalWidth = imagesx($image);
        $originalHeight = imagesy($image);

        // Skip if image is already smaller than target
        if ($originalWidth <= $maxWidth && $originalHeight <= $maxHeight) {
            imagedestroy($image);

            return $this->saveOriginal();
        }

        // Calculate new dimensions maintaining aspect ratio
        $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
        $newWidth = (int) round($originalWidth * $ratio);
        $newHeight = (int) round($originalHeight * $ratio);

        // Check memory for the resized image
        if (! $this->hasEnoughMemory($newWidth, $newHeight)) {
            Log::warning('ImageResizer: Insufficient memory for resized image');
            imagedestroy($image);

            return $this->saveOriginal();
        }

        // Create resized image
        $resized = imagecreatetruecolor($newWidth, $newHeight);

        if ($resized === false) {
            imagedestroy($image);

            return $this->saveOriginal();
        }

        // Preserve transparency for PNG/WebP
        if ($imageInfo['type'] === IMAGETYPE_PNG || $imageInfo['type'] === IMAGETYPE_WEBP) {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
            imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $transparent);
        }

        // Perform the resize
        $success = imagecopyresampled(
            $resized,
            $image,
            0,
            0,
            0,
            0,
            $newWidth,
            $newHeight,
            $originalWidth,
            $originalHeight
        );

        imagedestroy($image);

        if (! $success) {
            imagedestroy($resized);

            return $this->saveOriginal();
        }

        // Save the resized image
        $result = $this->saveImage($resized, $imageInfo['type']);
        imagedestroy($resized);

        return $result;
    }

    /**
     * Get image information from content.
     */
    protected function getImageInfo(): ?array
    {
        $info = @getimagesizefromstring($this->content);

        if ($info === false) {
            return null;
        }

        return [
            'width' => $info[0],
            'height' => $info[1],
            'type' => $info[2],
            'mime' => $info['mime'],
        ];
    }

    /**
     * Check if there is enough memory to process an image.
     */
    protected function hasEnoughMemory(int $width, int $height): bool
    {
        $required = $this->estimateRequiredMemory($width, $height);
        $available = $this->getAvailableMemory();

        return $available > ($required + self::MEMORY_BUFFER);
    }

    /**
     * Estimate the memory required to process an image.
     *
     * Based on image dimensions assuming 4 bytes per pixel (RGBA).
     */
    protected function estimateRequiredMemory(int $width, int $height): int
    {
        // 4 bytes per pixel (RGBA) * safety factor for GD operations
        return $width * $height * 4 * self::MEMORY_SAFETY_FACTOR;
    }

    /**
     * Get available memory in bytes.
     */
    protected function getAvailableMemory(): int
    {
        $limit = $this->parseMemoryLimit(ini_get('memory_limit'));

        if ($limit < 0) {
            // No memory limit
            return PHP_INT_MAX;
        }

        return $limit - memory_get_usage(true);
    }

    /**
     * Parse PHP memory limit string to bytes.
     */
    protected function parseMemoryLimit(string $limit): int
    {
        if ($limit === '-1') {
            return -1;
        }

        $limit = strtolower(trim($limit));
        $value = (int) $limit;

        $unit = substr($limit, -1);

        switch ($unit) {
            case 'g':
                $value *= 1024 * 1024 * 1024;
                break;
            case 'm':
                $value *= 1024 * 1024;
                break;
            case 'k':
                $value *= 1024;
                break;
        }

        return $value;
    }

    /**
     * Create a GD image resource from content.
     */
    protected function createImageFromContent(): ?\GdImage
    {
        $image = @imagecreatefromstring($this->content);

        if ($image === false) {
            return null;
        }

        return $image;
    }

    /**
     * Save the original content without modification.
     */
    protected function saveOriginal(): bool
    {
        return Storage::disk($this->disk)->put($this->path, $this->content);
    }

    /**
     * Save the GD image to the destination.
     */
    protected function saveImage(\GdImage $image, int $type): bool
    {
        // Enable progressive JPEG if configured
        if ($type === IMAGETYPE_JPEG && config('media.progressive_jpeg', true)) {
            imageinterlace($image, true);
        }

        ob_start();

        $success = match ($type) {
            IMAGETYPE_JPEG => imagejpeg($image, null, 85),
            IMAGETYPE_PNG => imagepng($image, null, 6),
            IMAGETYPE_WEBP => imagewebp($image, null, 85),
            IMAGETYPE_GIF => imagegif($image),
            default => false,
        };

        $content = ob_get_clean();

        if (! $success || $content === false || $content === '') {
            return false;
        }

        return Storage::disk($this->disk)->put($this->path, $content);
    }
}
