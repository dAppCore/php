<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Media\Image;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImageOptimizer
{
    /**
     * GD typically needs 5-6x the image size in memory.
     */
    protected const MEMORY_SAFETY_FACTOR = 6;

    /**
     * Minimum memory buffer to maintain (in bytes).
     */
    protected const MEMORY_BUFFER = 32 * 1024 * 1024; // 32MB

    protected string $driver;

    protected int $defaultQuality;

    protected int $pngCompression;

    protected int $minSizeKb;

    protected int|float $maxSizeMb;

    protected bool $enabled;

    public function __construct()
    {
        $this->enabled = config('images.optimization.enabled', true);
        $this->driver = config('images.optimization.driver', 'gd');
        $this->defaultQuality = config('images.optimization.quality', 80);
        $this->pngCompression = config('images.optimization.png_compression', 6);
        $this->minSizeKb = config('images.optimization.min_size_kb', 10);
        $this->maxSizeMb = config('images.optimization.max_size_mb', 10);

        // Validate driver availability
        if ($this->driver === 'imagick' && ! extension_loaded('imagick')) {
            Log::warning('ImageOptimizer: imagick driver selected but extension not loaded, falling back to gd');
            $this->driver = 'gd';
        }

        if ($this->driver === 'gd' && ! extension_loaded('gd')) {
            throw new \RuntimeException('ImageOptimizer: GD extension is not loaded');
        }
    }

    /**
     * Optimize an uploaded file.
     */
    public function optimizeUploadedFile(UploadedFile $file, array $options = []): OptimizationResult
    {
        // Get temporary path
        $tempPath = $file->getRealPath();

        return $this->optimizeFile($tempPath, $options);
    }

    /**
     * Optimize an image at the given path.
     *
     * @param  string  $path  Absolute file path
     * @param  array  $options  Override options (quality, driver, etc.)
     */
    public function optimize(string $path, array $options = []): OptimizationResult
    {
        if (! $this->enabled) {
            return $this->createNoOpResult($path);
        }

        // Handle both absolute paths and storage paths
        $absolutePath = $this->resolveAbsolutePath($path);

        return $this->optimizeFile($absolutePath, $options);
    }

    /**
     * Optimize a file and replace it in place.
     */
    protected function optimizeFile(string $absolutePath, array $options = []): OptimizationResult
    {
        if (! file_exists($absolutePath)) {
            throw new \InvalidArgumentException("File not found: {$absolutePath}");
        }

        $originalSize = filesize($absolutePath);

        // Check size constraints
        if ($originalSize < ($this->minSizeKb * 1024)) {
            Log::debug("ImageOptimizer: Skipping file smaller than {$this->minSizeKb}KB", ['path' => $absolutePath]);

            return $this->createNoOpResult($absolutePath);
        }

        if ($originalSize > ($this->maxSizeMb * 1024 * 1024)) {
            Log::debug("ImageOptimizer: Skipping file larger than {$this->maxSizeMb}MB", ['path' => $absolutePath]);

            return $this->createNoOpResult($absolutePath);
        }

        // Detect image type
        $imageInfo = @getimagesize($absolutePath);
        if (! $imageInfo) {
            throw new \InvalidArgumentException("Not a valid image: {$absolutePath}");
        }

        // Check memory before processing
        $width = $imageInfo[0];
        $height = $imageInfo[1];

        if (! $this->hasEnoughMemory($width, $height)) {
            Log::warning('ImageOptimizer: Insufficient memory for image processing', [
                'path' => $absolutePath,
                'width' => $width,
                'height' => $height,
                'estimated_memory' => $this->formatBytes($this->estimateRequiredMemory($width, $height)),
                'available_memory' => $this->formatBytes($this->getAvailableMemory()),
            ]);

            return $this->createNoOpResult($absolutePath);
        }

        $mimeType = $imageInfo['mime'];
        $quality = $options['quality'] ?? $this->defaultQuality;
        $driver = $options['driver'] ?? $this->driver;

        // Optimize based on mime type
        try {
            $optimizedSize = match ($mimeType) {
                'image/jpeg', 'image/jpg' => $this->optimizeJpeg($absolutePath, $quality, $driver),
                'image/png' => $this->optimizePng($absolutePath, $this->pngCompression, $driver),
                'image/webp' => $this->optimizeWebp($absolutePath, $quality, $driver),
                default => null,
            };

            if ($optimizedSize === null) {
                Log::debug("ImageOptimizer: Unsupported mime type: {$mimeType}");

                return $this->createNoOpResult($absolutePath);
            }

            // Calculate savings
            $percentageSaved = $originalSize > 0
                ? (int) round((($originalSize - $optimizedSize) / $originalSize) * 100)
                : 0;

            // Ensure we don't report negative savings
            $percentageSaved = max(0, $percentageSaved);

            return new OptimizationResult(
                originalSize: $originalSize,
                optimizedSize: $optimizedSize,
                percentageSaved: $percentageSaved,
                path: $absolutePath,
                driver: $driver,
            );
        } catch (\Exception $e) {
            Log::error('ImageOptimizer: Optimization failed', [
                'path' => $absolutePath,
                'error' => $e->getMessage(),
            ]);

            // Return no-op result on failure
            return $this->createNoOpResult($absolutePath);
        }
    }

    /**
     * Optimize JPEG image.
     */
    protected function optimizeJpeg(string $path, int $quality, string $driver): int
    {
        if ($driver === 'gd') {
            $image = @imagecreatefromjpeg($path);
            if (! $image) {
                throw new \RuntimeException('Failed to create image from JPEG');
            }

            // Enable progressive JPEG if configured
            if (config('media.progressive_jpeg', true)) {
                imageinterlace($image, true);
            }

            // Save with compression
            $success = imagejpeg($image, $path, $quality);
            imagedestroy($image);

            if (! $success) {
                throw new \RuntimeException('Failed to save optimised JPEG');
            }

            return filesize($path);
        }

        // Imagick implementation would go here
        throw new \RuntimeException('Only GD driver is currently implemented for JPEG');
    }

    /**
     * Optimize PNG image.
     */
    protected function optimizePng(string $path, int $compression, string $driver): int
    {
        if ($driver === 'gd') {
            $image = @imagecreatefrompng($path);
            if (! $image) {
                throw new \RuntimeException('Failed to create image from PNG');
            }

            // PNG compression level: 0 (no compression) to 9 (max compression)
            // Config uses 0-9 scale
            $success = imagepng($image, $path, $compression);
            imagedestroy($image);

            if (! $success) {
                throw new \RuntimeException('Failed to save optimised PNG');
            }

            return filesize($path);
        }

        // Imagick implementation would go here
        throw new \RuntimeException('Only GD driver is currently implemented for PNG');
    }

    /**
     * Optimize WebP image.
     */
    protected function optimizeWebp(string $path, int $quality, string $driver): int
    {
        if ($driver === 'gd') {
            $image = @imagecreatefromwebp($path);
            if (! $image) {
                throw new \RuntimeException('Failed to create image from WebP');
            }

            $success = imagewebp($image, $path, $quality);
            imagedestroy($image);

            if (! $success) {
                throw new \RuntimeException('Failed to save optimised WebP');
            }

            return filesize($path);
        }

        // Imagick implementation would go here
        throw new \RuntimeException('Only GD driver is currently implemented for WebP');
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
     * Format bytes to human-readable size.
     */
    protected function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.'B';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).'KB';
        }

        if ($bytes < 1024 * 1024 * 1024) {
            return round($bytes / (1024 * 1024), 1).'MB';
        }

        return round($bytes / (1024 * 1024 * 1024), 2).'GB';
    }

    /**
     * Create a no-op result (no optimization performed).
     */
    protected function createNoOpResult(string $path): OptimizationResult
    {
        $size = file_exists($path) ? filesize($path) : 0;

        return new OptimizationResult(
            originalSize: $size,
            optimizedSize: $size,
            percentageSaved: 0,
            path: $path,
            driver: $this->driver,
        );
    }

    /**
     * Resolve path to absolute filesystem path.
     */
    protected function resolveAbsolutePath(string $path): string
    {
        // If already absolute, return as-is
        if (str_starts_with($path, '/')) {
            return $path;
        }

        // If it's a storage path, resolve it
        if (Storage::exists($path)) {
            return Storage::path($path);
        }

        // Try to resolve relative to storage/app
        $storagePath = storage_path('app/'.$path);
        if (file_exists($storagePath)) {
            return $storagePath;
        }

        // Return as-is and let the caller handle the error
        return $path;
    }

    /**
     * Get optimization statistics for a workspace.
     *
     * @param  Model|null  $workspace  Optional workspace model to filter by
     */
    public function getStats(?Model $workspace = null): array
    {
        return ImageOptimization::getWorkspaceStats($workspace);
    }

    /**
     * Track optimization in database.
     *
     * @param  Model|null  $workspace  Optional workspace model
     * @param  Model|null  $optimizable  Optional related model
     */
    public function recordOptimization(
        OptimizationResult $result,
        ?Model $workspace = null,
        ?Model $optimizable = null,
        ?string $originalPath = null
    ): ImageOptimization {
        return ImageOptimization::create([
            'path' => $result->path,
            'original_path' => $originalPath,
            'original_size' => $result->originalSize,
            'optimized_size' => $result->optimizedSize,
            'percentage_saved' => $result->percentageSaved,
            'driver' => $result->driver,
            'quality' => $this->defaultQuality,
            'workspace_id' => $workspace?->id,
            'optimizable_type' => $optimizable !== null ? get_class($optimizable) : null,
            'optimizable_id' => $optimizable?->id,
        ]);
    }
}
