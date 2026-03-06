<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Media\Thumbnail;

use Core\Media\Jobs\GenerateThumbnail;
use Core\Media\Support\ImageResizer;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Lazy thumbnail generation service.
 *
 * Generates thumbnails on-demand when first requested, rather than eagerly
 * on upload. Supports caching, queue-based generation for large images,
 * and graceful fallback handling.
 *
 * ## Usage
 *
 * ```php
 * $lazyThumb = new LazyThumbnail();
 *
 * // Get or generate a thumbnail
 * $path = $lazyThumb->get('uploads/image.jpg', 200, 200);
 *
 * // Get a signed URL for lazy thumbnail route
 * $url = $lazyThumb->url('uploads/image.jpg', 200, 200);
 *
 * // Check if thumbnail exists
 * if ($lazyThumb->exists('uploads/image.jpg', 200, 200)) {
 *     // Thumbnail is already generated
 * }
 * ```
 *
 * ## Configuration
 *
 * Configure via `config/images.php`:
 * - `lazy_thumbnails.enabled` - Enable/disable lazy generation
 * - `lazy_thumbnails.queue_threshold_kb` - Size threshold for queueing
 * - `lazy_thumbnails.cache_ttl` - How long to cache thumbnail paths
 * - `lazy_thumbnails.placeholder` - Placeholder image path
 */
class LazyThumbnail
{
    /**
     * Default disk for source images.
     */
    protected string $sourceDisk = 'public';

    /**
     * Disk for storing generated thumbnails.
     */
    protected string $thumbnailDisk = 'public';

    /**
     * Directory prefix for thumbnails.
     */
    protected string $thumbnailPrefix = 'thumbnails';

    /**
     * JPEG quality for generated thumbnails.
     */
    protected int $quality = 85;

    /**
     * Supported image MIME types.
     */
    protected const SUPPORTED_MIMES = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/webp',
    ];

    /**
     * Create a new LazyThumbnail instance.
     */
    public function __construct(
        ?string $sourceDisk = null,
        ?string $thumbnailDisk = null
    ) {
        $this->sourceDisk = $sourceDisk ?? config('images.lazy_thumbnails.source_disk', 'public');
        $this->thumbnailDisk = $thumbnailDisk ?? config('images.lazy_thumbnails.thumbnail_disk', 'public');
        $this->thumbnailPrefix = config('images.lazy_thumbnails.prefix', 'thumbnails');
        $this->quality = config('images.lazy_thumbnails.quality', 85);
    }

    /**
     * Get or generate a thumbnail for the given image.
     *
     * Returns the thumbnail path if it exists or was generated successfully.
     * Returns null if generation failed or is queued.
     *
     * @param  string  $sourcePath  Path to the source image
     * @param  int  $width  Target width in pixels
     * @param  int  $height  Target height in pixels
     * @param  bool  $async  If true, queue generation instead of blocking
     * @return string|null The thumbnail path or null if queued/failed
     */
    public function get(string $sourcePath, int $width, int $height, bool $async = false): ?string
    {
        // Check if thumbnail already exists
        $thumbnailPath = $this->getThumbnailPath($sourcePath, $width, $height);

        if ($this->thumbnailExists($thumbnailPath)) {
            return $thumbnailPath;
        }

        // Validate source exists and is supported
        if (! $this->canGenerate($sourcePath)) {
            return null;
        }

        // Check if we should queue this
        if ($async || $this->shouldQueue($sourcePath)) {
            $this->queueGeneration($sourcePath, $width, $height);

            return null;
        }

        // Generate synchronously
        return $this->generate($sourcePath, $width, $height);
    }

    /**
     * Generate a thumbnail synchronously.
     *
     * @param  string  $sourcePath  Path to the source image
     * @param  int  $width  Target width in pixels
     * @param  int  $height  Target height in pixels
     * @return string|null The thumbnail path or null if generation failed
     */
    public function generate(string $sourcePath, int $width, int $height): ?string
    {
        $thumbnailPath = $this->getThumbnailPath($sourcePath, $width, $height);

        // Check cache for in-progress generation
        $cacheKey = $this->getCacheKey($sourcePath, $width, $height);
        if (Cache::has($cacheKey.':generating')) {
            return null;
        }

        // Mark as generating to prevent duplicate processing
        Cache::put($cacheKey.':generating', true, 60);

        try {
            $content = $this->getSourceDisk()->get($sourcePath);

            if ($content === null) {
                Log::warning('LazyThumbnail: Source file not found', [
                    'source' => $sourcePath,
                ]);

                return null;
            }

            // Ensure thumbnail directory exists
            $this->ensureDirectoryExists($thumbnailPath);

            // Resize the image
            $success = ImageResizer::make($content)
                ->disk($this->thumbnailDisk)
                ->path($thumbnailPath)
                ->resize($width, $height);

            if ($success) {
                // Cache the thumbnail path
                $cacheTtl = config('images.lazy_thumbnails.cache_ttl', 86400);
                Cache::put($cacheKey, $thumbnailPath, $cacheTtl);

                Log::debug('LazyThumbnail: Generated thumbnail', [
                    'source' => $sourcePath,
                    'thumbnail' => $thumbnailPath,
                    'dimensions' => "{$width}x{$height}",
                ]);

                return $thumbnailPath;
            }

            Log::warning('LazyThumbnail: Failed to generate thumbnail', [
                'source' => $sourcePath,
                'dimensions' => "{$width}x{$height}",
            ]);

            return null;
        } catch (\Throwable $e) {
            Log::error('LazyThumbnail: Exception during generation', [
                'source' => $sourcePath,
                'error' => $e->getMessage(),
            ]);

            return null;
        } finally {
            Cache::forget($cacheKey.':generating');
        }
    }

    /**
     * Queue thumbnail generation for later processing.
     *
     * @param  string  $sourcePath  Path to the source image
     * @param  int  $width  Target width in pixels
     * @param  int  $height  Target height in pixels
     */
    public function queueGeneration(string $sourcePath, int $width, int $height): void
    {
        $cacheKey = $this->getCacheKey($sourcePath, $width, $height);

        // Don't queue if already queued or generating
        if (Cache::has($cacheKey.':queued') || Cache::has($cacheKey.':generating')) {
            return;
        }

        Cache::put($cacheKey.':queued', true, 300);

        $queue = config('images.lazy_thumbnails.queue_name', 'default');

        GenerateThumbnail::dispatch($sourcePath, $width, $height, [
            'source_disk' => $this->sourceDisk,
            'thumbnail_disk' => $this->thumbnailDisk,
            'prefix' => $this->thumbnailPrefix,
            'quality' => $this->quality,
        ])->onQueue($queue);

        Log::debug('LazyThumbnail: Queued thumbnail generation', [
            'source' => $sourcePath,
            'dimensions' => "{$width}x{$height}",
            'queue' => $queue,
        ]);
    }

    /**
     * Check if a thumbnail exists.
     *
     * @param  string  $sourcePath  Path to the source image
     * @param  int  $width  Target width
     * @param  int  $height  Target height
     */
    public function exists(string $sourcePath, int $width, int $height): bool
    {
        $thumbnailPath = $this->getThumbnailPath($sourcePath, $width, $height);

        return $this->thumbnailExists($thumbnailPath);
    }

    /**
     * Get the URL for a lazy thumbnail.
     *
     * Returns a signed URL to the lazy thumbnail route that will generate
     * the thumbnail on first request.
     *
     * @param  string  $sourcePath  Path to the source image
     * @param  int  $width  Target width
     * @param  int  $height  Target height
     * @return string The signed URL
     */
    public function url(string $sourcePath, int $width, int $height): string
    {
        // If thumbnail exists, return direct URL
        if ($this->exists($sourcePath, $width, $height)) {
            $thumbnailPath = $this->getThumbnailPath($sourcePath, $width, $height);

            return $this->getThumbnailDisk()->url($thumbnailPath);
        }

        // Return URL to lazy generation route
        return $this->getRouteUrl($sourcePath, $width, $height);
    }

    /**
     * Get the placeholder image URL or path.
     *
     * @param  int|null  $width  Optional width for placeholder
     * @param  int|null  $height  Optional height for placeholder
     * @return string|null Placeholder URL/path or null if not configured
     */
    public function getPlaceholder(?int $width = null, ?int $height = null): ?string
    {
        $placeholder = config('images.lazy_thumbnails.placeholder');

        if ($placeholder === null) {
            return null;
        }

        // If it's a URL, return as-is
        if (Str::startsWith($placeholder, ['http://', 'https://', '//'])) {
            return $placeholder;
        }

        // If it's a path, check if it exists
        $disk = $this->getThumbnailDisk();
        if ($disk->exists($placeholder)) {
            return $disk->url($placeholder);
        }

        return null;
    }

    /**
     * Delete a generated thumbnail.
     *
     * @param  string  $sourcePath  Path to the source image
     * @param  int  $width  Target width
     * @param  int  $height  Target height
     */
    public function delete(string $sourcePath, int $width, int $height): bool
    {
        $thumbnailPath = $this->getThumbnailPath($sourcePath, $width, $height);
        $cacheKey = $this->getCacheKey($sourcePath, $width, $height);

        // Clear cache
        Cache::forget($cacheKey);

        // Delete file if it exists
        if ($this->thumbnailExists($thumbnailPath)) {
            return $this->getThumbnailDisk()->delete($thumbnailPath);
        }

        return true;
    }

    /**
     * Delete all thumbnails for a source image.
     *
     * @param  string  $sourcePath  Path to the source image
     */
    public function deleteAll(string $sourcePath): int
    {
        $directory = $this->getThumbnailDirectory($sourcePath);
        $disk = $this->getThumbnailDisk();

        if (! $disk->exists($directory)) {
            return 0;
        }

        $files = $disk->files($directory);
        $deleted = 0;

        foreach ($files as $file) {
            if ($disk->delete($file)) {
                $deleted++;
            }
        }

        // Try to remove empty directory
        if (empty($disk->files($directory))) {
            $disk->deleteDirectory($directory);
        }

        return $deleted;
    }

    /**
     * Purge stale thumbnails older than the specified age.
     *
     * @param  int  $maxAgeDays  Maximum age in days
     * @return int Number of thumbnails deleted
     */
    public function purgeStale(int $maxAgeDays = 30): int
    {
        $disk = $this->getThumbnailDisk();
        $cutoff = now()->subDays($maxAgeDays)->timestamp;
        $deleted = 0;

        $files = $disk->allFiles($this->thumbnailPrefix);

        foreach ($files as $file) {
            $lastModified = $disk->lastModified($file);

            if ($lastModified < $cutoff) {
                if ($disk->delete($file)) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    /**
     * Check if the source image can be processed.
     */
    public function canGenerate(string $sourcePath): bool
    {
        $disk = $this->getSourceDisk();

        if (! $disk->exists($sourcePath)) {
            return false;
        }

        $mimeType = $disk->mimeType($sourcePath);

        return in_array($mimeType, self::SUPPORTED_MIMES, true);
    }

    /**
     * Check if the source file size exceeds the queue threshold.
     */
    protected function shouldQueue(string $sourcePath): bool
    {
        $thresholdKb = config('images.lazy_thumbnails.queue_threshold_kb', 500);

        if ($thresholdKb <= 0) {
            return false;
        }

        $fileSize = $this->getSourceDisk()->size($sourcePath);
        $thresholdBytes = $thresholdKb * 1024;

        return $fileSize > $thresholdBytes;
    }

    /**
     * Get the thumbnail path for given source and dimensions.
     */
    public function getThumbnailPath(string $sourcePath, int $width, int $height): string
    {
        $directory = $this->getThumbnailDirectory($sourcePath);
        $filename = pathinfo($sourcePath, PATHINFO_FILENAME);
        $extension = pathinfo($sourcePath, PATHINFO_EXTENSION) ?: 'jpg';

        return "{$directory}/{$filename}_{$width}x{$height}.{$extension}";
    }

    /**
     * Get the thumbnail directory for a source image.
     */
    protected function getThumbnailDirectory(string $sourcePath): string
    {
        // Create a hash-based subdirectory to avoid too many files in one folder
        $hash = substr(md5($sourcePath), 0, 4);

        return "{$this->thumbnailPrefix}/{$hash}";
    }

    /**
     * Check if a thumbnail file exists.
     */
    protected function thumbnailExists(string $thumbnailPath): bool
    {
        return $this->getThumbnailDisk()->exists($thumbnailPath);
    }

    /**
     * Ensure the directory for a thumbnail path exists.
     */
    protected function ensureDirectoryExists(string $thumbnailPath): void
    {
        $directory = dirname($thumbnailPath);
        $disk = $this->getThumbnailDisk();

        if (! $disk->exists($directory)) {
            $disk->makeDirectory($directory);
        }
    }

    /**
     * Get the cache key for a thumbnail.
     */
    protected function getCacheKey(string $sourcePath, int $width, int $height): string
    {
        return 'lazy_thumb:'.md5("{$this->sourceDisk}:{$sourcePath}:{$width}x{$height}");
    }

    /**
     * Get the URL for the lazy thumbnail route.
     */
    protected function getRouteUrl(string $sourcePath, int $width, int $height): string
    {
        $params = [
            'path' => base64_encode($sourcePath),
            'w' => $width,
            'h' => $height,
        ];

        // Add signature for security
        $signature = $this->generateSignature($sourcePath, $width, $height);
        $params['sig'] = $signature;

        return url('/media/thumb?'.http_build_query($params));
    }

    /**
     * Generate a signature for URL validation.
     */
    public function generateSignature(string $sourcePath, int $width, int $height): string
    {
        $key = config('app.key');
        $data = "{$sourcePath}:{$width}:{$height}";

        return substr(hash_hmac('sha256', $data, $key), 0, 16);
    }

    /**
     * Verify a URL signature.
     */
    public function verifySignature(string $sourcePath, int $width, int $height, string $signature): bool
    {
        $expected = $this->generateSignature($sourcePath, $width, $height);

        return hash_equals($expected, $signature);
    }

    /**
     * Get the source disk filesystem instance.
     */
    protected function getSourceDisk(): Filesystem
    {
        return Storage::disk($this->sourceDisk);
    }

    /**
     * Get the thumbnail disk filesystem instance.
     */
    protected function getThumbnailDisk(): Filesystem
    {
        return Storage::disk($this->thumbnailDisk);
    }

    /**
     * Set the source disk.
     */
    public function sourceDisk(string $disk): static
    {
        $this->sourceDisk = $disk;

        return $this;
    }

    /**
     * Set the thumbnail disk.
     */
    public function thumbnailDisk(string $disk): static
    {
        $this->thumbnailDisk = $disk;

        return $this;
    }

    /**
     * Set the thumbnail prefix/directory.
     */
    public function prefix(string $prefix): static
    {
        $this->thumbnailPrefix = $prefix;

        return $this;
    }

    /**
     * Set the JPEG quality.
     */
    public function quality(int $quality): static
    {
        $this->quality = max(1, min(100, $quality));

        return $this;
    }

    /**
     * Get statistics about generated thumbnails.
     *
     * @return array{count: int, total_size: int, total_size_human: string}
     */
    public function getStats(): array
    {
        $disk = $this->getThumbnailDisk();
        $files = $disk->allFiles($this->thumbnailPrefix);
        $totalSize = 0;

        foreach ($files as $file) {
            $totalSize += $disk->size($file);
        }

        return [
            'count' => count($files),
            'total_size' => $totalSize,
            'total_size_human' => $this->formatBytes($totalSize),
        ];
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
}
