<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Cdn\Services;

use Core\Cdn\Models\StorageOffload as OffloadModel;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Storage offload service for migrating local files to remote storage.
 *
 * Handles:
 * - Tracking which files have been offloaded
 * - Uploading local files to remote storage
 * - URL resolution for offloaded files
 * - Cleanup of local files after offload
 */
class StorageOffload
{
    protected string $disk;

    protected bool $enabled;

    protected bool $keepLocal;

    protected ?string $cdnUrl;

    protected ?int $maxFileSize;

    protected ?array $allowedExtensions;

    protected bool $cacheEnabled;

    public function __construct()
    {
        $this->disk = config('offload.disk') ?? 'hetzner-public';
        $this->enabled = config('offload.enabled') ?? false;
        $this->keepLocal = config('offload.keep_local') ?? true;
        $this->cdnUrl = config('offload.cdn_url');
        $this->maxFileSize = config('offload.max_file_size');
        $this->allowedExtensions = config('offload.allowed_extensions');
        $this->cacheEnabled = config('offload.cache.enabled') ?? false;
    }

    /**
     * Check if storage offload is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Get the configured disk name.
     */
    public function getDiskName(): string
    {
        return $this->disk;
    }

    /**
     * Get the disk instance.
     */
    public function getDisk(): Filesystem
    {
        return Storage::disk($this->disk);
    }

    /**
     * Check if a local file has been offloaded.
     */
    public function isOffloaded(string $localPath): bool
    {
        return OffloadModel::where('local_path', $localPath)->exists();
    }

    /**
     * Get the offload record for a file.
     */
    public function getRecord(string $localPath): ?OffloadModel
    {
        return OffloadModel::where('local_path', $localPath)->first();
    }

    /**
     * Get the remote URL for an offloaded file.
     */
    public function url(string $localPath): ?string
    {
        // Check cache first
        if ($this->cacheEnabled) {
            $cached = Cache::get("offload_url:{$localPath}");
            if ($cached !== null) {
                return $cached ?: null; // Empty string means no record
            }
        }

        $record = OffloadModel::where('local_path', $localPath)->first();

        if (! $record) {
            if ($this->cacheEnabled) {
                Cache::put("offload_url:{$localPath}", '', 3600);
            }

            return null;
        }

        // Use CDN URL if configured, otherwise fall back to disk URL
        if ($this->cdnUrl) {
            $url = rtrim($this->cdnUrl, '/').'/'.ltrim($record->remote_path, '/');
        } else {
            $url = Storage::disk($this->disk)->url($record->remote_path);
        }

        if ($this->cacheEnabled) {
            Cache::put("offload_url:{$localPath}", $url, 3600);
        }

        return $url;
    }

    /**
     * Upload a local file to remote storage.
     *
     * @param  string  $localPath  Absolute path to local file
     * @param  string|null  $remotePath  Custom remote path (auto-generated if null)
     * @param  string  $category  Category for path prefixing
     * @param  array  $metadata  Additional metadata to store
     * @return OffloadModel|null The offload record on success
     */
    public function upload(string $localPath, ?string $remotePath = null, string $category = 'media', array $metadata = []): ?OffloadModel
    {
        if (! $this->enabled) {
            Log::debug('StorageOffload: Offload disabled');

            return null;
        }

        if (! File::exists($localPath)) {
            Log::warning('StorageOffload: Local file not found', ['path' => $localPath]);

            return null;
        }

        $fileSize = File::size($localPath);

        // Check max file size
        if ($this->maxFileSize !== null && $fileSize > $this->maxFileSize) {
            Log::debug('StorageOffload: File exceeds max size', [
                'path' => $localPath,
                'size' => $fileSize,
                'max' => $this->maxFileSize,
            ]);

            return null;
        }

        // Check allowed extensions
        $extension = strtolower(pathinfo($localPath, PATHINFO_EXTENSION));
        if ($this->allowedExtensions !== null && ! in_array($extension, $this->allowedExtensions)) {
            Log::debug('StorageOffload: Extension not allowed', [
                'path' => $localPath,
                'extension' => $extension,
                'allowed' => $this->allowedExtensions,
            ]);

            return null;
        }

        // Check if already offloaded
        if ($this->isOffloaded($localPath)) {
            Log::debug('StorageOffload: File already offloaded', ['path' => $localPath]);

            return OffloadModel::where('local_path', $localPath)->first();
        }

        // Generate remote path if not provided
        $remotePath = $remotePath ?? $this->generateRemotePath($localPath, $category);

        try {
            // Read file contents
            $contents = File::get($localPath);
            $hash = hash('sha256', $contents);
            $mimeType = File::mimeType($localPath);

            // Upload to remote storage
            $disk = Storage::disk($this->disk);
            $uploaded = $disk->put($remotePath, $contents);

            if (! $uploaded) {
                Log::error('StorageOffload: Upload failed', [
                    'local' => $localPath,
                    'remote' => $remotePath,
                ]);

                return null;
            }

            // Merge original_name into metadata only if not already set
            if (! isset($metadata['original_name'])) {
                $metadata['original_name'] = basename($localPath);
            }

            // Create tracking record
            $record = OffloadModel::create([
                'local_path' => $localPath,
                'remote_path' => $remotePath,
                'disk' => $this->disk,
                'hash' => $hash,
                'file_size' => $fileSize,
                'mime_type' => $mimeType,
                'category' => $category,
                'metadata' => $metadata,
                'offloaded_at' => now(),
            ]);

            // Delete local file if not keeping
            if (! $this->keepLocal) {
                File::delete($localPath);
            }

            Log::info('StorageOffload: File offloaded successfully', [
                'local' => $localPath,
                'remote' => $remotePath,
            ]);

            return $record;
        } catch (\Exception $e) {
            Log::error('StorageOffload: Exception during upload', [
                'path' => $localPath,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Batch upload multiple files.
     *
     * @param  array<string>  $localPaths  List of local paths
     * @param  string  $category  Category for path prefixing
     * @return array{uploaded: int, failed: int, skipped: int}
     */
    public function uploadBatch(array $localPaths, string $category = 'media'): array
    {
        $results = [
            'uploaded' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        foreach ($localPaths as $path) {
            if ($this->isOffloaded($path)) {
                $results['skipped']++;

                continue;
            }

            $record = $this->upload($path, null, $category);

            if ($record) {
                $results['uploaded']++;
            } else {
                $results['failed']++;
            }
        }

        return $results;
    }

    /**
     * Generate a remote path for a local file.
     */
    protected function generateRemotePath(string $localPath, string $category): string
    {
        $extension = pathinfo($localPath, PATHINFO_EXTENSION);
        $hash = Str::random(16);

        // Date-based partitioning
        $datePath = date('Y/m');

        // Add 's' suffix to category for plural paths
        $categoryPath = $category;
        if (! str_ends_with($categoryPath, 's')) {
            $categoryPath .= 's';
        }

        return "{$categoryPath}/{$datePath}/{$hash}.{$extension}";
    }

    /**
     * Get all offloaded files for a category.
     *
     * @return \Illuminate\Database\Eloquent\Collection<OffloadModel>
     */
    public function getByCategory(string $category)
    {
        return OffloadModel::where('category', $category)->get();
    }

    /**
     * Delete an offloaded file from remote storage.
     */
    public function delete(string $localPath): bool
    {
        $record = OffloadModel::where('local_path', $localPath)->first();

        if (! $record) {
            return false;
        }

        try {
            // Delete from remote storage
            Storage::disk($this->disk)->delete($record->remote_path);

            // Delete tracking record
            $record->delete();

            // Clear cache
            if ($this->cacheEnabled) {
                Cache::forget("offload_url:{$localPath}");
            }

            return true;
        } catch (\Exception $e) {
            Log::error('StorageOffload: Delete failed', [
                'path' => $localPath,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Verify file integrity by comparing hash.
     */
    public function verifyIntegrity(string $localPath): bool
    {
        $record = OffloadModel::where('local_path', $localPath)->first();

        if (! $record) {
            return false;
        }

        try {
            $remoteContents = Storage::disk($this->disk)->get($record->remote_path);
            $remoteHash = hash('sha256', $remoteContents);

            return hash_equals($record->hash, $remoteHash);
        } catch (\Exception $e) {
            Log::error('StorageOffload: Integrity check failed', [
                'path' => $localPath,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get storage statistics.
     */
    public function getStats(): array
    {
        $totalFiles = OffloadModel::count();
        $totalSize = OffloadModel::sum('file_size');

        $byCategory = OffloadModel::selectRaw('category, COUNT(*) as count, SUM(file_size) as total_size')
            ->groupBy('category')
            ->get()
            ->keyBy('category')
            ->toArray();

        return [
            'total_files' => $totalFiles,
            'total_size' => $totalSize,
            'total_size_human' => $this->formatBytes($totalSize),
            'by_category' => $byCategory,
        ];
    }

    /**
     * Format bytes to human-readable string.
     */
    protected function formatBytes(int|string|null $bytes): string
    {
        $bytes = (int) ($bytes ?? 0);
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
        $power = min($power, count($units) - 1);

        return round($bytes / (1024 ** $power), 2).' '.$units[$power];
    }
}
