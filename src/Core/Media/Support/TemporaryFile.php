<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Media\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Temporary file handler for media processing.
 *
 * Creates and manages temporary files during media conversion operations.
 */
class TemporaryFile
{
    protected string $path;

    protected string $directory;

    public function __construct(?string $path = null)
    {
        $this->directory = sys_get_temp_dir().'/media-'.Str::random(16);
        mkdir($this->directory, 0755, true);

        $this->path = $path ?? $this->directory.'/temp-'.Str::random(8);
    }

    /**
     * Create a new temporary file instance.
     */
    public static function make(): static
    {
        return new static;
    }

    /**
     * Copy a file from a disk to the temporary location.
     */
    public function fromDisk(string $sourceDisk, string $sourceFilepath): static
    {
        $disk = Storage::disk($sourceDisk);
        $extension = pathinfo($sourceFilepath, PATHINFO_EXTENSION);

        $this->path = $this->directory.'/temp-'.Str::random(8).'.'.$extension;

        $content = $disk->get($sourceFilepath);
        file_put_contents($this->path, $content);

        return $this;
    }

    /**
     * Get the temporary file path.
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * Get a directory helper for cleanup.
     */
    public function directory(): TemporaryDirectory
    {
        return new TemporaryDirectory($this->directory);
    }
}
