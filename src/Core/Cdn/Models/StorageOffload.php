<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Cdn\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Tracks files that have been offloaded to remote storage.
 *
 * @property int $id
 * @property string $local_path Original local file path
 * @property string $remote_path Path in remote storage
 * @property string $disk Laravel disk name
 * @property string|null $hash SHA-256 hash of file contents
 * @property int|null $file_size File size in bytes
 * @property string|null $mime_type MIME type
 * @property string|null $category Category for path prefixing
 * @property array|null $metadata Additional metadata
 * @property \Illuminate\Support\Carbon|null $offloaded_at When file was offloaded
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class StorageOffload extends Model
{
    use HasFactory;

    protected $table = 'storage_offloads';

    protected $fillable = [
        'local_path',
        'remote_path',
        'disk',
        'hash',
        'file_size',
        'mime_type',
        'category',
        'metadata',
        'offloaded_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'file_size' => 'integer',
        'offloaded_at' => 'datetime',
    ];

    /**
     * Get the category.
     */
    public function getCategory(): ?string
    {
        return $this->category;
    }

    /**
     * Get metadata value by key.
     */
    public function getMetadata(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->metadata;
        }

        return $this->metadata[$key] ?? null;
    }

    /**
     * Get the original filename from metadata.
     */
    public function getOriginalName(): ?string
    {
        return $this->getMetadata('original_name');
    }

    /**
     * Check if this offload is for a specific category.
     */
    public function isCategory(string $category): bool
    {
        return $this->getCategory() === $category;
    }

    /**
     * Check if the file is an image.
     */
    public function isImage(): bool
    {
        return $this->mime_type && str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Check if the file is a video.
     */
    public function isVideo(): bool
    {
        return $this->mime_type && str_starts_with($this->mime_type, 'video/');
    }

    /**
     * Check if the file is audio.
     */
    public function isAudio(): bool
    {
        return $this->mime_type && str_starts_with($this->mime_type, 'audio/');
    }

    /**
     * Scope to filter by category.
     */
    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Alias for scopeCategory - filter by category.
     */
    public function scopeInCategory($query, string $category)
    {
        return $this->scopeCategory($query, $category);
    }

    /**
     * Scope to filter by disk.
     */
    public function scopeDisk($query, string $disk)
    {
        return $query->where('disk', $disk);
    }

    /**
     * Alias for scopeDisk - filter by disk.
     */
    public function scopeForDisk($query, string $disk)
    {
        return $this->scopeDisk($query, $disk);
    }

    /**
     * Get human-readable file size.
     */
    public function getFileSizeHumanAttribute(): string
    {
        $bytes = $this->file_size ?? 0;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
        $power = min($power, count($units) - 1);

        return round($bytes / (1024 ** $power), 2).' '.$units[$power];
    }
}
