<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Cdn\Console;

use Illuminate\Console\Command;

class CdnPurge extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cdn:purge
                            {workspace? : Workspace slug or "all" to purge all workspaces}
                            {--url=* : Specific URL(s) to purge}
                            {--tag= : Purge by cache tag}
                            {--everything : Purge entire CDN cache (use with caution)}
                            {--dry-run : Show what would be purged without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Purge content from CDN edge cache';

    /**
     * Purger instance (Core\Plug\Cdn\Bunny\Purge when available).
     */
    protected ?object $purger = null;

    public function __construct()
    {
        parent::__construct();

        if (class_exists(\Core\Plug\Cdn\Bunny\Purge::class)) {
            $this->purger = new \Core\Plug\Cdn\Bunny\Purge;
        }
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->purger === null) {
            $this->error('CDN Purge requires Core\Plug\Cdn\Bunny\Purge class. Plug module not installed.');

            return self::FAILURE;
        }

        $workspaceArg = $this->argument('workspace');
        $urls = $this->option('url');
        $tag = $this->option('tag');
        $everything = $this->option('everything');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('Dry run mode - no changes will be made');
            $this->newLine();
        }

        // Check for mutually exclusive options
        $optionCount = ($everything ? 1 : 0) + (! empty($urls) ? 1 : 0) + (! empty($tag) ? 1 : 0) + (! empty($workspaceArg) ? 1 : 0);
        if ($optionCount > 1 && $everything) {
            $this->error('Cannot use --everything with other options');

            return self::FAILURE;
        }

        // Purge everything
        if ($everything) {
            return $this->purgeEverything($dryRun);
        }

        // Purge specific URLs
        if (! empty($urls)) {
            return $this->purgeUrls($urls, $dryRun);
        }

        // Purge by tag
        if (! empty($tag)) {
            return $this->purgeByTag($tag, $dryRun);
        }

        // Purge by workspace
        if (empty($workspaceArg)) {
            $workspaceOptions = ['all', 'Select specific URLs'];
            if (class_exists(\Core\Tenant\Models\Workspace::class)) {
                $workspaceOptions = array_merge($workspaceOptions, \Core\Tenant\Models\Workspace::pluck('slug')->toArray());
            }
            $workspaceArg = $this->choice(
                'What would you like to purge?',
                $workspaceOptions,
                0
            );

            if ($workspaceArg === 'Select specific URLs') {
                $urlInput = $this->ask('Enter URL(s) to purge (comma-separated)');
                $urls = array_map('trim', explode(',', $urlInput));

                return $this->purgeUrls($urls, $dryRun);
            }
        }

        if ($workspaceArg === 'all') {
            return $this->purgeAllWorkspaces($dryRun);
        }

        return $this->purgeWorkspace($workspaceArg, $dryRun);
    }

    protected function purgeEverything(bool $dryRun): int
    {
        if (! $dryRun && ! $this->confirm('Are you sure you want to purge the ENTIRE CDN cache? This will affect all content.', false)) {
            $this->info('Aborted');

            return self::SUCCESS;
        }

        $this->warn('Purging entire CDN cache...');

        if ($dryRun) {
            $this->info('Would purge: entire CDN cache');

            return self::SUCCESS;
        }

        try {
            $result = $this->purger->all();

            if ($result->isOk()) {
                $this->info('CDN cache purged successfully');

                return self::SUCCESS;
            }

            $this->error('Failed to purge CDN cache: '.$result->message());

            return self::FAILURE;
        } catch (\Exception $e) {
            $this->error("Purge failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    protected function purgeUrls(array $urls, bool $dryRun): int
    {
        $this->info('Purging '.count($urls).' URL(s)...');

        foreach ($urls as $url) {
            $this->line("  - {$url}");
        }

        if ($dryRun) {
            return self::SUCCESS;
        }

        try {
            $result = $this->purger->urls($urls);

            if ($result->isOk()) {
                $this->newLine();
                $this->info('URLs purged successfully');

                return self::SUCCESS;
            }

            $this->error('Failed to purge URLs: '.$result->message());

            return self::FAILURE;
        } catch (\Exception $e) {
            $this->error("Purge failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    protected function purgeByTag(string $tag, bool $dryRun): int
    {
        $this->info("Purging cache tag: {$tag}");

        if ($dryRun) {
            $this->info("Would purge: all content with tag '{$tag}'");

            return self::SUCCESS;
        }

        try {
            $result = $this->purger->tag($tag);

            if ($result->isOk()) {
                $this->info('Cache tag purged successfully');

                return self::SUCCESS;
            }

            $this->error('Failed to purge cache tag: '.$result->message());

            return self::FAILURE;
        } catch (\Exception $e) {
            $this->error("Purge failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    protected function purgeAllWorkspaces(bool $dryRun): int
    {
        if (! class_exists(\Core\Tenant\Models\Workspace::class)) {
            $this->error('Workspace purge requires Tenant module to be installed.');

            return self::FAILURE;
        }

        $workspaces = \Core\Tenant\Models\Workspace::all();

        if ($workspaces->isEmpty()) {
            $this->error('No workspaces found');

            return self::FAILURE;
        }

        $this->info("Purging {$workspaces->count()} workspaces...");
        $this->newLine();

        $success = true;

        foreach ($workspaces as $workspace) {
            $this->line("Workspace: <info>{$workspace->slug}</info>");

            if ($dryRun) {
                $this->line("  Would purge: workspace-{$workspace->uuid}");

                continue;
            }

            try {
                $result = $this->purger->workspace($workspace->uuid);

                if ($result->isOk()) {
                    $this->line('  <fg=green>Purged</>');
                } else {
                    $this->line('  <fg=red>Failed: '.$result->message().'</>');
                    $success = false;
                }
            } catch (\Exception $e) {
                $this->line("  <fg=red>Error: {$e->getMessage()}</>");
                $success = false;
            }
        }

        $this->newLine();

        if ($success) {
            $this->info('All workspaces purged successfully');

            return self::SUCCESS;
        }

        $this->warn('Some workspaces failed to purge');

        return self::FAILURE;
    }

    protected function purgeWorkspace(string $slug, bool $dryRun): int
    {
        if (! class_exists(\Core\Tenant\Models\Workspace::class)) {
            $this->error('Workspace purge requires Tenant module to be installed.');

            return self::FAILURE;
        }

        $workspace = \Core\Tenant\Models\Workspace::where('slug', $slug)->first();

        if (! $workspace) {
            $this->error("Workspace not found: {$slug}");
            $this->newLine();
            $this->info('Available workspaces:');
            \Core\Tenant\Models\Workspace::pluck('slug')->each(fn ($s) => $this->line("  - {$s}"));

            return self::FAILURE;
        }

        $this->info("Purging workspace: {$workspace->slug}");

        if ($dryRun) {
            $this->line("Would purge: workspace-{$workspace->uuid}");

            return self::SUCCESS;
        }

        try {
            $result = $this->purger->workspace($workspace->uuid);

            if ($result->isOk()) {
                $this->info('Workspace cache purged successfully');

                return self::SUCCESS;
            }

            $this->error('Failed to purge workspace cache: '.$result->message());

            return self::FAILURE;
        } catch (\Exception $e) {
            $this->error("Purge failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
