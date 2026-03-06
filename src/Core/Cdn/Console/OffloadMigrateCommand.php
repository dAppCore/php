<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Cdn\Console;

use Core\Cdn\Services\StorageOffload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class OffloadMigrateCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'offload:migrate
                            {path? : Directory to scan for files}
                            {--category=media : Category for offloaded files}
                            {--dry-run : Show what would be offloaded without actually doing it}
                            {--force : Skip confirmation prompt}
                            {--only-missing : Only offload files not already offloaded}';

    /**
     * The console command description.
     */
    protected $description = 'Migrate local files to remote storage';

    protected StorageOffload $offloadService;

    /**
     * Execute the console command.
     */
    public function handle(StorageOffload $offloadService): int
    {
        $this->offloadService = $offloadService;

        if (! $this->offloadService->isEnabled()) {
            $this->error('Storage offload is not enabled in configuration.');
            $this->info('Set STORAGE_OFFLOAD_ENABLED=true in your .env file.');

            return self::FAILURE;
        }

        // Determine path to scan
        $path = $this->argument('path') ?? storage_path('app/public');
        $category = $this->option('category');
        $dryRun = $this->option('dry-run');
        $onlyMissing = $this->option('only-missing');

        if (! is_dir($path)) {
            $this->error("Directory not found: {$path}");

            return self::FAILURE;
        }

        $this->info("Scanning directory: {$path}");
        $this->info("Category: {$category}");
        $this->info("Disk: {$this->offloadService->getDiskName()}");

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No files will be offloaded');
        }

        $this->line('');

        // Scan for files
        $files = $this->scanDirectory($path);

        if (empty($files)) {
            $this->info('No eligible files found.');

            return self::SUCCESS;
        }

        $this->info('Found '.count($files).' file(s) to process.');

        // Filter already offloaded files if requested
        if ($onlyMissing) {
            $files = array_filter($files, function ($file) {
                return ! $this->offloadService->isOffloaded($file);
            });

            if (empty($files)) {
                $this->info('All files are already offloaded.');

                return self::SUCCESS;
            }

            $this->info('Found '.count($files).' file(s) not yet offloaded.');
        }

        // Calculate total size
        $totalSize = array_sum(array_map('filesize', $files));
        $this->info('Total size: '.$this->formatBytes($totalSize));
        $this->line('');

        // Confirmation
        if (! $dryRun && ! $this->option('force')) {
            if (! $this->confirm('Proceed with offloading?')) {
                $this->info('Cancelled.');

                return self::SUCCESS;
            }
        }

        // Process files
        $processed = 0;
        $failed = 0;
        $skipped = 0;

        $this->withProgressBar($files, function ($file) use ($category, $dryRun, &$processed, &$failed, &$skipped) {
            // Check if already offloaded
            if ($this->offloadService->isOffloaded($file)) {
                $skipped++;

                return;
            }

            if ($dryRun) {
                $processed++;

                return;
            }

            // Attempt to offload
            $result = $this->offloadService->upload($file, null, $category);

            if ($result) {
                $processed++;
            } else {
                $failed++;
            }
        });

        $this->newLine(2);

        // Summary
        $this->info('Migration complete!');
        $this->table(
            ['Status', 'Count'],
            [
                ['Processed', $processed],
                ['Failed', $failed],
                ['Skipped', $skipped],
                ['Total', count($files)],
            ]
        );

        if ($failed > 0) {
            $this->warn('Some files failed to offload. Check logs for details.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Scan directory recursively for eligible files.
     */
    protected function scanDirectory(string $path): array
    {
        $files = [];
        $allowedExtensions = config('offload.allowed_extensions', []);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $filePath = $file->getPathname();
            $extension = strtolower($file->getExtension());

            // Skip if not in allowed extensions list (if configured)
            if (! empty($allowedExtensions) && ! in_array($extension, $allowedExtensions)) {
                continue;
            }

            // Skip if exceeds max file size
            $maxSize = config('offload.max_file_size', 100 * 1024 * 1024);
            if ($file->getSize() > $maxSize) {
                continue;
            }

            $files[] = $filePath;
        }

        return $files;
    }

    /**
     * Format bytes to human-readable format.
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
        $power = min($power, count($units) - 1);

        return round($bytes / (1024 ** $power), 2).' '.$units[$power];
    }
}
