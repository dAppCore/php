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
use Illuminate\Support\Facades\File;

class PushAssetsToCdn extends Command
{
    protected $signature = 'cdn:push-assets
                            {--domain=host.uk.com : Workspace domain for vBucket scoping}
                            {--flux : Push Flux UI assets only}
                            {--fontawesome : Push Font Awesome assets only}
                            {--js : Push JavaScript assets only}
                            {--all : Push all static assets (default)}
                            {--dry-run : Show what would be uploaded without uploading}';

    protected $description = 'Push static assets to CDN storage zone for edge delivery';

    protected StorageUrlResolver $cdn;

    /**
     * VBucket instance (Core\Plug\Storage\Bunny\VBucket when available).
     */
    protected ?object $vbucket = null;

    protected bool $dryRun = false;

    protected int $uploadCount = 0;

    protected int $failCount = 0;

    public function handle(FluxCdnService $flux, StorageUrlResolver $cdn): int
    {
        if (! class_exists(\Core\Plug\Storage\Bunny\VBucket::class)) {
            $this->error('Push assets to CDN requires Core\Plug\Storage\Bunny\VBucket class. Plug module not installed.');

            return self::FAILURE;
        }

        $this->cdn = $cdn;
        $this->dryRun = $this->option('dry-run');

        // Create vBucket for workspace isolation
        $domain = $this->option('domain');
        $this->vbucket = \Core\Plug\Storage\Bunny\VBucket::public($domain);

        $pushFlux = $this->option('flux');
        $pushFontawesome = $this->option('fontawesome');
        $pushJs = $this->option('js');
        $pushAll = $this->option('all') || (! $pushFlux && ! $pushFontawesome && ! $pushJs);

        $this->info("Pushing assets to CDN storage zone for {$domain}...");
        $this->line("vBucket: {$this->vbucket->id()}");
        $this->newLine();

        if ($pushAll || $pushFlux) {
            $this->pushFluxAssets($flux);
        }

        if ($pushAll || $pushFontawesome) {
            $this->pushFontAwesomeAssets();
        }

        if ($pushAll || $pushJs) {
            $this->pushJsAssets();
        }

        $this->newLine();

        if ($this->dryRun) {
            $this->info("Dry run complete. Would upload {$this->uploadCount} files.");
        } else {
            $this->info("Upload complete. {$this->uploadCount} files uploaded, {$this->failCount} failed.");
            $this->line('CDN URL: '.config('cdn.urls.cdn'));
        }

        return $this->failCount > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function pushFluxAssets(FluxCdnService $flux): void
    {
        $this->components->info('Flux UI assets');

        $assets = $flux->getCdnAssetPaths();

        foreach ($assets as $sourcePath => $cdnPath) {
            $this->uploadFile($sourcePath, $cdnPath);
        }
    }

    protected function pushFontAwesomeAssets(): void
    {
        $this->components->info('Font Awesome assets');

        $basePath = public_path('vendor/fontawesome');

        if (! File::isDirectory($basePath)) {
            $this->warn('  Font Awesome directory not found at public/vendor/fontawesome');

            return;
        }

        // Push CSS files
        $cssPath = "{$basePath}/css";
        if (File::isDirectory($cssPath)) {
            foreach (File::files($cssPath) as $file) {
                $cdnPath = 'vendor/fontawesome/css/'.$file->getFilename();
                $this->uploadFile($file->getPathname(), $cdnPath);
            }
        }

        // Push webfonts
        $webfontsPath = "{$basePath}/webfonts";
        if (File::isDirectory($webfontsPath)) {
            foreach (File::files($webfontsPath) as $file) {
                $cdnPath = 'vendor/fontawesome/webfonts/'.$file->getFilename();
                $this->uploadFile($file->getPathname(), $cdnPath);
            }
        }
    }

    protected function pushJsAssets(): void
    {
        $this->components->info('JavaScript assets');

        $jsPath = public_path('js');

        if (! File::isDirectory($jsPath)) {
            $this->warn('  JavaScript directory not found at public/js');

            return;
        }

        foreach (File::files($jsPath) as $file) {
            if ($file->getExtension() === 'js') {
                $cdnPath = 'js/'.$file->getFilename();
                $this->uploadFile($file->getPathname(), $cdnPath);
            }
        }
    }

    protected function uploadFile(string $sourcePath, string $cdnPath): void
    {
        if (! file_exists($sourcePath)) {
            $this->warn("  ✗ Source not found: {$sourcePath}");
            $this->failCount++;

            return;
        }

        $size = $this->formatBytes(filesize($sourcePath));

        if ($this->dryRun) {
            $this->line("  [DRY-RUN] {$cdnPath} ({$size})");
            $this->uploadCount++;

            return;
        }

        // Push directly to CDN storage zone via vBucket (workspace-isolated)
        $contents = file_get_contents($sourcePath);
        $result = $this->vbucket->putContents($cdnPath, $contents);

        if ($result->isOk()) {
            $this->line("  ✓ {$cdnPath} ({$size})");
            $this->uploadCount++;
        } else {
            $this->error("  ✗ Failed: {$cdnPath}");
            $this->failCount++;
        }
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
