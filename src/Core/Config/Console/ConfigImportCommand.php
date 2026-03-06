<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Config\Console;

use Core\Config\ConfigExporter;
use Core\Config\ConfigVersioning;
use Illuminate\Console\Command;
use Symfony\Component\Console\Completion\CompletionInput;

/**
 * Import config from JSON or YAML file.
 *
 * Usage:
 *   php artisan config:import config.json
 *   php artisan config:import config.yaml --workspace=myworkspace
 *   php artisan config:import backup.json --dry-run
 */
class ConfigImportCommand extends Command
{
    protected $signature = 'config:import
                            {file : Input file path (.json or .yaml/.yml)}
                            {--workspace= : Import config for specific workspace slug}
                            {--dry-run : Preview changes without applying}
                            {--no-backup : Skip creating a version backup before import}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Import config from JSON or YAML file';

    public function handle(ConfigExporter $exporter, ConfigVersioning $versioning): int
    {
        $file = $this->argument('file');
        $workspaceSlug = $this->option('workspace');
        $dryRun = $this->option('dry-run');
        $skipBackup = $this->option('no-backup');
        $force = $this->option('force');

        // Check file exists
        if (! file_exists($file)) {
            $this->components->error("File not found: {$file}");

            return self::FAILURE;
        }

        // Resolve workspace
        $workspace = null;
        if ($workspaceSlug) {
            if (! class_exists(\Core\Tenant\Models\Workspace::class)) {
                $this->components->error('Tenant module not installed. Cannot import workspace config.');

                return self::FAILURE;
            }

            $workspace = \Core\Tenant\Models\Workspace::where('slug', $workspaceSlug)->first();

            if (! $workspace) {
                $this->components->error("Workspace not found: {$workspaceSlug}");

                return self::FAILURE;
            }
        }

        // Read file content
        $content = file_get_contents($file);
        if ($content === false) {
            $this->components->error("Failed to read file: {$file}");

            return self::FAILURE;
        }

        // Determine format from extension
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $format = match ($extension) {
            'yaml', 'yml' => 'YAML',
            default => 'JSON',
        };

        $scope = $workspace ? "workspace: {$workspace->slug}" : 'system';

        if ($dryRun) {
            $this->components->info("Dry-run import from {$file} ({$scope}):");
        } else {
            if (! $force) {
                $this->components->warn("This will import config from {$file} to {$scope}.");

                if (! $this->confirm('Are you sure you want to continue?')) {
                    $this->components->info('Import cancelled.');

                    return self::SUCCESS;
                }
            }

            // Create backup before import
            if (! $skipBackup && ! $dryRun) {
                $this->components->task('Creating backup version', function () use ($versioning, $workspace, $file) {
                    $versioning->createVersion(
                        $workspace,
                        'Backup before import from '.basename($file)
                    );
                });
            }
        }

        // Perform import
        $result = null;
        $this->components->task("Importing {$format} config", function () use ($exporter, $content, $extension, $workspace, $dryRun, &$result) {
            $result = match ($extension) {
                'yaml', 'yml' => $exporter->importYaml($content, $workspace, $dryRun),
                default => $exporter->importJson($content, $workspace, $dryRun),
            };
        });

        // Show results
        $this->newLine();

        if ($dryRun) {
            $this->components->info('Dry-run results (no changes applied):');
        }

        // Display created items
        if ($result->createdCount() > 0) {
            $this->components->twoColumnDetail('<fg=green>Created</>', $result->createdCount().' items');
            foreach ($result->getCreated() as $item) {
                $this->components->bulletList(["{$item['type']}: {$item['code']}"]);
            }
        }

        // Display updated items
        if ($result->updatedCount() > 0) {
            $this->components->twoColumnDetail('<fg=yellow>Updated</>', $result->updatedCount().' items');
            foreach ($result->getUpdated() as $item) {
                $this->components->bulletList(["{$item['type']}: {$item['code']}"]);
            }
        }

        // Display skipped items
        if ($result->skippedCount() > 0) {
            $this->components->twoColumnDetail('<fg=gray>Skipped</>', $result->skippedCount().' items');
            foreach ($result->getSkipped() as $reason) {
                $this->components->bulletList([$reason]);
            }
        }

        // Display errors
        if ($result->hasErrors()) {
            $this->newLine();
            $this->components->error('Errors:');
            foreach ($result->getErrors() as $error) {
                $this->components->bulletList(["<fg=red>{$error}</>"]);
            }

            return self::FAILURE;
        }

        $this->newLine();

        if ($dryRun) {
            $this->components->info("Dry-run complete: {$result->getSummary()}");
        } else {
            $this->components->info("Import complete: {$result->getSummary()}");
        }

        return self::SUCCESS;
    }

    /**
     * Get autocompletion suggestions.
     */
    public function complete(CompletionInput $input, \Symfony\Component\Console\Completion\CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestOptionValuesFor('workspace')) {
            if (class_exists(\Core\Tenant\Models\Workspace::class)) {
                $suggestions->suggestValues(\Core\Tenant\Models\Workspace::pluck('slug')->toArray());
            }
        }
    }
}
