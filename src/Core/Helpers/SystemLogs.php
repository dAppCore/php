<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Helpers;

use Illuminate\Support\Str;

/**
 * System log file reader.
 *
 * Provides access to Laravel log files with size warnings
 * for oversized files.
 */
class SystemLogs
{
    /**
     * Maximum safe log file size (3MB).
     */
    private const MAX_SAFE_SIZE = 3145728;

    /**
     * Read all log files from storage/logs.
     *
     * @return array<int, array{name: string, contents: string, error: string}>
     */
    public function logs(): array
    {
        $files = $this->getFilePaths();

        $logs = [];

        foreach ($files as $file) {
            $filename = basename($file);
            $size = filesize($file);

            $error = '';

            if ($size >= self::MAX_SAFE_SIZE) {
                $humanSize = $this->formatBytes($size);
                $error = "Warning: Error log file {$filename} is {$humanSize}";
            }

            $handle = fopen($file, 'r');

            $logs[] = [
                'name' => $filename,
                'contents' => fread($handle, self::MAX_SAFE_SIZE),
                'error' => $error,
            ];

            fclose($handle);
        }

        return $logs;
    }

    /**
     * Get base path for log files.
     */
    public function basePathForLogs(): string
    {
        return Str::finish(realpath(storage_path('logs')), DIRECTORY_SEPARATOR);
    }

    /**
     * Get full file path for a log file.
     */
    public function getFilePath(string $name): string
    {
        return $this->basePathForLogs().$name;
    }

    /**
     * Get all log file paths.
     *
     * @return array<int, string>|false
     */
    protected function getFilePaths(): array|false
    {
        $files = glob($this->basePathForLogs().'*.log');

        $files = array_map('realpath', $files);

        return array_filter($files, 'is_file');
    }

    /**
     * Format bytes to human-readable size.
     */
    protected function formatBytes(int $bytes): string
    {
        $suffixes = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];

        $i = 0;
        $size = (float) $bytes;

        while (($size / 1024) > 1) {
            $size = $size / 1024;
            $i++;
        }

        return round($size, 2).$suffixes[$i];
    }
}
