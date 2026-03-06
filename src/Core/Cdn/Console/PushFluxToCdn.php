<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Cdn\Console;

use Core\Cdn\Services\FluxCdnService;
use Core\Cdn\Services\StorageUrlResolver;
use Illuminate\Console\Command;

class PushFluxToCdn extends Command
{
    protected $signature = 'cdn:push-flux {--dry-run : Show what would be uploaded without uploading}';

    protected $description = 'Push Flux UI assets to CDN storage zone';

    public function handle(FluxCdnService $flux, StorageUrlResolver $cdn): int
    {
        $this->info('Pushing Flux assets to CDN...');

        $assets = $flux->getCdnAssetPaths();

        if (empty($assets)) {
            $this->warn('No Flux assets found to push.');

            return self::SUCCESS;
        }

        $dryRun = $this->option('dry-run');

        foreach ($assets as $sourcePath => $cdnPath) {
            if (! file_exists($sourcePath)) {
                $this->warn("Source file not found: {$sourcePath}");

                continue;
            }

            $size = $this->formatBytes(filesize($sourcePath));

            if ($dryRun) {
                $this->line("  [DRY-RUN] Would upload: {$cdnPath} ({$size})");

                continue;
            }

            $this->line("  Uploading: {$cdnPath} ({$size})");

            $contents = file_get_contents($sourcePath);
            $success = $cdn->storePublic($cdnPath, $contents, pushToCdn: true);

            if ($success) {
                $this->info('    ✓ Uploaded to CDN');
            } else {
                $this->error('    ✗ Failed to upload');
            }
        }

        if (! $dryRun) {
            $this->newLine();
            $this->info('Flux assets pushed to CDN successfully.');
            $this->line('CDN URL: '.config('cdn.urls.cdn').'/flux/');
        }

        return self::SUCCESS;
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2).' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 2).' KB';
        }

        return $bytes.' bytes';
    }
}
