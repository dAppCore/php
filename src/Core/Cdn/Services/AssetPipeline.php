<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Cdn\Services;

use Core\Cdn\Jobs\PushAssetToCdn;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Asset processing pipeline for the dual-bucket CDN architecture.
 *
 * Flow:
 * 1. Store raw upload -> private bucket (optional, for processing)
 * 2. Process (resize, optimize, etc.) -> handled by caller
 * 3. Store processed -> public bucket
 * 4. Push to CDN storage zone
 *
 * Categories define path prefixes:
 * - media: General media uploads
 * - social: Social media assets
 * - page: Page builder assets
 * - avatar: User/workspace avatars
 * - content: ContentMedia
 * - static: Static assets
 * - widget: Widget assets
 *
 * ## Methods
 *
 * | Method | Returns | Description |
 * |--------|---------|-------------|
 * | `store()` | `array` | Process and store an uploaded file to public bucket |
 * | `storeContents()` | `array` | Store raw content (string/stream) to public bucket |
 * | `storePrivate()` | `array` | Store to private bucket for DRM/gated content |
 * | `copy()` | `array` | Copy file between buckets |
 * | `delete()` | `bool` | Delete an asset from storage and CDN |
 * | `deleteMany()` | `array` | Delete multiple assets |
 * | `urls()` | `array` | Get CDN and origin URLs for a path |
 * | `exists()` | `bool` | Check if a file exists in storage |
 * | `size()` | `int\|null` | Get file size in bytes |
 * | `mimeType()` | `string\|null` | Get file MIME type |
 */
class AssetPipeline
{
    protected StorageUrlResolver $urlResolver;

    /**
     * Storage manager instance (Core\Plug\Storage\StorageManager when available).
     */
    protected ?object $storage = null;

    public function __construct(StorageUrlResolver $urlResolver, ?object $storage = null)
    {
        $this->urlResolver = $urlResolver;
        $this->storage = $storage;
    }

    /**
     * Process and store an uploaded file.
     *
     * @param  UploadedFile  $file  The uploaded file
     * @param  string  $category  Category key (media, social, page, etc.)
     * @param  string|null  $filename  Custom filename (auto-generated if null)
     * @param  array  $options  Additional options (workspace_id, user_id, etc.)
     * @return array{path: string, cdn_url: string, origin_url: string, size: int, mime: string}
     */
    public function store(UploadedFile $file, string $category, ?string $filename = null, array $options = []): array
    {
        $filename = $filename ?? $this->generateFilename($file);
        $path = $this->buildPath($category, $filename, $options);

        // Store to public bucket
        $stored = $this->urlResolver->publicDisk()->putFileAs(
            dirname($path),
            $file,
            basename($path)
        );

        if (! $stored) {
            throw new \RuntimeException("Failed to store file at: {$path}");
        }

        // Queue CDN push if enabled
        $this->queueCdnPush('hetzner-public', $path, 'public');

        return [
            'path' => $path,
            'cdn_url' => $this->urlResolver->cdn($path),
            'origin_url' => $this->urlResolver->origin($path),
            'size' => $file->getSize(),
            'mime' => $file->getMimeType(),
        ];
    }

    /**
     * Store raw content (string or stream).
     *
     * @param  string|resource  $contents  File contents
     * @param  string  $category  Category key
     * @param  string  $filename  Filename with extension
     * @param  array  $options  Additional options
     * @return array{path: string, cdn_url: string, origin_url: string}
     */
    public function storeContents($contents, string $category, string $filename, array $options = []): array
    {
        $path = $this->buildPath($category, $filename, $options);

        $stored = $this->urlResolver->publicDisk()->put($path, $contents);

        if (! $stored) {
            throw new \RuntimeException("Failed to store content at: {$path}");
        }

        $this->queueCdnPush('hetzner-public', $path, 'public');

        return [
            'path' => $path,
            'cdn_url' => $this->urlResolver->cdn($path),
            'origin_url' => $this->urlResolver->origin($path),
        ];
    }

    /**
     * Store to private bucket (for DRM/gated content).
     *
     * @param  UploadedFile|string|resource  $content  File or contents
     * @param  string  $category  Category key
     * @param  string|null  $filename  Filename (required for non-UploadedFile)
     * @param  array  $options  Additional options
     * @return array{path: string, private_url: string}
     */
    public function storePrivate($content, string $category, ?string $filename = null, array $options = []): array
    {
        if ($content instanceof UploadedFile) {
            $filename = $filename ?? $this->generateFilename($content);
            $path = $this->buildPath($category, $filename, $options);

            $stored = $this->urlResolver->privateDisk()->putFileAs(
                dirname($path),
                $content,
                basename($path)
            );
        } else {
            if (! $filename) {
                throw new \InvalidArgumentException('Filename required for non-UploadedFile content');
            }

            $path = $this->buildPath($category, $filename, $options);
            $stored = $this->urlResolver->privateDisk()->put($path, $content);
        }

        if (! $stored) {
            throw new \RuntimeException("Failed to store private content at: {$path}");
        }

        $this->queueCdnPush('hetzner-private', $path, 'private');

        return [
            'path' => $path,
            'private_url' => $this->urlResolver->private($path),
        ];
    }

    /**
     * Copy an existing file from one bucket to another.
     *
     * @param  string  $sourcePath  Source path
     * @param  string  $sourceBucket  Source bucket ('public' or 'private')
     * @param  string  $destBucket  Destination bucket ('public' or 'private')
     * @param  string|null  $destPath  Destination path (same as source if null)
     * @return array{path: string, bucket: string}
     *
     * @throws \RuntimeException If source file not found or copy fails
     */
    public function copy(string $sourcePath, string $sourceBucket, string $destBucket, ?string $destPath = null): array
    {
        $sourceDisk = $sourceBucket === 'private'
            ? $this->urlResolver->privateDisk()
            : $this->urlResolver->publicDisk();

        $destDisk = $destBucket === 'private'
            ? $this->urlResolver->privateDisk()
            : $this->urlResolver->publicDisk();

        $destPath = $destPath ?? $sourcePath;

        $contents = $sourceDisk->get($sourcePath);

        if ($contents === null) {
            throw new \RuntimeException("Source file not found: {$sourcePath}");
        }

        $stored = $destDisk->put($destPath, $contents);

        if (! $stored) {
            throw new \RuntimeException("Failed to copy to: {$destPath}");
        }

        $hetznerDisk = $destBucket === 'private' ? 'hetzner-private' : 'hetzner-public';
        $this->queueCdnPush($hetznerDisk, $destPath, $destBucket);

        return [
            'path' => $destPath,
            'bucket' => $destBucket,
        ];
    }

    /**
     * Delete an asset from storage and CDN.
     *
     * @param  string  $path  File path
     * @param  string  $bucket  'public' or 'private'
     * @return bool True if deletion was successful
     */
    public function delete(string $path, string $bucket = 'public'): bool
    {
        return $this->urlResolver->deleteAsset($path, $bucket);
    }

    /**
     * Delete multiple assets.
     *
     * @param  array<string>  $paths  File paths
     * @param  string  $bucket  'public' or 'private'
     * @return array<string, bool> Map of path to deletion success status
     */
    public function deleteMany(array $paths, string $bucket = 'public'): array
    {
        $results = [];
        $disk = $bucket === 'private'
            ? $this->urlResolver->privateDisk()
            : $this->urlResolver->publicDisk();

        foreach ($paths as $path) {
            $results[$path] = $disk->delete($path);
        }

        // Bulk delete from CDN storage (requires StorageManager from Plug module)
        if ($this->storage !== null) {
            $this->storage->zone($bucket)->delete()->paths($paths);
        }

        // Purge from CDN cache if enabled
        if (config('cdn.pipeline.auto_purge', true)) {
            foreach ($paths as $path) {
                $this->urlResolver->purge($path);
            }
        }

        return $results;
    }

    /**
     * Get URLs for a path.
     *
     * @param  string  $path  File path
     * @return array{cdn: string, origin: string}
     */
    public function urls(string $path): array
    {
        return $this->urlResolver->urls($path);
    }

    /**
     * Build storage path from category and filename.
     *
     * @param  string  $category  Category key (media, social, etc.)
     * @param  string  $filename  Filename with extension
     * @param  array<string, mixed>  $options  Options including workspace_id, user_id
     * @return string Full storage path
     */
    protected function buildPath(string $category, string $filename, array $options = []): string
    {
        $prefix = $this->urlResolver->pathPrefix($category);
        $parts = [$prefix];

        // Add workspace scope if provided
        if (isset($options['workspace_id'])) {
            $parts[] = 'ws_'.$options['workspace_id'];
        }

        // Add user scope if provided
        if (isset($options['user_id'])) {
            $parts[] = 'u_'.$options['user_id'];
        }

        // Add date partitioning for media files
        if (in_array($category, ['media', 'social', 'content'])) {
            $parts[] = date('Y/m');
        }

        $parts[] = $filename;

        return implode('/', $parts);
    }

    /**
     * Generate a unique filename.
     *
     * @param  UploadedFile  $file  The uploaded file
     * @return string Unique filename with original extension
     */
    protected function generateFilename(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $hash = Str::random(16);

        return "{$hash}.{$extension}";
    }

    /**
     * Queue a CDN push job if auto-push is enabled.
     *
     * @param  string  $disk  Laravel disk name
     * @param  string  $path  Path within the disk
     * @param  string  $zone  Target CDN zone ('public' or 'private')
     */
    protected function queueCdnPush(string $disk, string $path, string $zone): void
    {
        if (! config('cdn.pipeline.auto_push', true)) {
            return;
        }

        if (! config('cdn.bunny.push_enabled', false)) {
            return;
        }

        $queue = config('cdn.pipeline.queue');

        if ($queue) {
            PushAssetToCdn::dispatch($disk, $path, $zone);
        } elseif ($this->storage !== null) {
            // Synchronous push if no queue configured (requires StorageManager from Plug module)
            $diskInstance = \Illuminate\Support\Facades\Storage::disk($disk);
            if ($diskInstance->exists($path)) {
                $contents = $diskInstance->get($path);
                $this->storage->zone($zone)->upload()->contents($path, $contents);
            }
        }
    }

    /**
     * Check if a file exists in storage.
     *
     * @param  string  $path  File path
     * @param  string  $bucket  'public' or 'private'
     * @return bool True if file exists
     */
    public function exists(string $path, string $bucket = 'public'): bool
    {
        $disk = $bucket === 'private'
            ? $this->urlResolver->privateDisk()
            : $this->urlResolver->publicDisk();

        return $disk->exists($path);
    }

    /**
     * Get file size in bytes.
     *
     * @param  string  $path  File path
     * @param  string  $bucket  'public' or 'private'
     */
    public function size(string $path, string $bucket = 'public'): ?int
    {
        $disk = $bucket === 'private'
            ? $this->urlResolver->privateDisk()
            : $this->urlResolver->publicDisk();

        return $disk->exists($path) ? $disk->size($path) : null;
    }

    /**
     * Get file MIME type.
     *
     * @param  string  $path  File path
     * @param  string  $bucket  'public' or 'private'
     */
    public function mimeType(string $path, string $bucket = 'public'): ?string
    {
        $disk = $bucket === 'private'
            ? $this->urlResolver->privateDisk()
            : $this->urlResolver->publicDisk();

        return $disk->exists($path) ? $disk->mimeType($path) : null;
    }
}
