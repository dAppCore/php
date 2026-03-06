<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Media\Image;

class OptimizationResult
{
    public function __construct(
        public readonly int $originalSize,
        public readonly int $optimizedSize,
        public readonly int $percentageSaved,
        public readonly string $path,
        public readonly string $driver,
    ) {}

    /**
     * Check if optimization was successful (saved space).
     */
    public function wasSuccessful(): bool
    {
        return $this->percentageSaved > 0;
    }

    /**
     * Get the number of bytes saved.
     */
    public function bytesSaved(): int
    {
        return $this->originalSize - $this->optimizedSize;
    }

    /**
     * Get human-readable summary.
     */
    public function getSummary(): string
    {
        return sprintf(
            '%d%% saved (%s → %s)',
            $this->percentageSaved,
            $this->formatBytes($this->originalSize),
            $this->formatBytes($this->optimizedSize)
        );
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
     * Convert to array for logging or storage.
     */
    public function toArray(): array
    {
        return [
            'original_size' => $this->originalSize,
            'optimized_size' => $this->optimizedSize,
            'percentage_saved' => $this->percentageSaved,
            'path' => $this->path,
            'driver' => $this->driver,
            'bytes_saved' => $this->bytesSaved(),
        ];
    }
}
