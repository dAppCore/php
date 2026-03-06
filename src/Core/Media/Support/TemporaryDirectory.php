<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Media\Support;

/**
 * Temporary directory handler for cleanup operations.
 */
class TemporaryDirectory
{
    public function __construct(protected string $path) {}

    /**
     * Delete the temporary directory and all its contents.
     */
    public function delete(): bool
    {
        if (! is_dir($this->path)) {
            return false;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        return rmdir($this->path);
    }

    /**
     * Get the directory path.
     */
    public function path(): string
    {
        return $this->path;
    }
}
