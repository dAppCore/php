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
use Core\Config\Models\ConfigKey;
use Core\Tenant\Models\Workspace;
use Illuminate\Console\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;

/**
 * Export config to JSON or YAML file.
 *
 * Usage:
 *   php artisan config:export config.json
 *   php artisan config:export config.yaml --workspace=myworkspace
 *   php artisan config:export backup.json --include-sensitive
 */
class ConfigExportCommand extends Command
{
    protected $signature = 'config:export
                            {file : Output file path (.json or .yaml/.yml)}
                            {--workspace= : Export config for specific workspace slug}
                            {--category= : Export only a specific category}
                            {--include-sensitive : Include sensitive values (WARNING: security risk)}
                            {--no-keys : Exclude key definitions, only export values}';

    protected $description = 'Export config to JSON or YAML file';

    public function handle(ConfigExporter $exporter): int
    {
        $file = $this->argument('file');
        $workspaceSlug = $this->option('workspace');
        $category = $this->option('category');
        $includeSensitive = $this->option('include-sensitive');
        $includeKeys = ! $this->option('no-keys');

        // Resolve workspace
        $workspace = null;
        if ($workspaceSlug) {
            if (! class_exists(Workspace::class)) {
                $this->components->error('Tenant module not installed. Cannot export workspace config.');

                return self::FAILURE;
            }

            $workspace = Workspace::where('slug', $workspaceSlug)->first();

            if (! $workspace) {
                $this->components->error("Workspace not found: {$workspaceSlug}");

                return self::FAILURE;
            }
        }

        // Warn about sensitive data
        if ($includeSensitive) {
            $this->components->warn('WARNING: Export will include sensitive values. Handle the file securely!');

            if (! $this->confirm('Are you sure you want to include sensitive values?')) {
                $this->components->info('Export cancelled.');

                return self::SUCCESS;
            }
        }

        // Determine format from extension
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $format = match ($extension) {
            'yaml', 'yml' => 'YAML',
            default => 'JSON',
        };

        $this->components->task("Exporting {$format} config", function () use ($exporter, $file, $workspace, $includeSensitive, $includeKeys, $category) {
            $content = match (strtolower(pathinfo($file, PATHINFO_EXTENSION))) {
                'yaml', 'yml' => $exporter->exportYaml($workspace, $includeSensitive, $includeKeys, $category),
                default => $exporter->exportJson($workspace, $includeSensitive, $includeKeys, $category),
            };

            file_put_contents($file, $content);
        });

        $scope = $workspace ? "workspace: {$workspace->slug}" : 'system';
        $this->components->info("Config exported to {$file} ({$scope})");

        return self::SUCCESS;
    }

    /**
     * Get autocompletion suggestions.
     */
    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestOptionValuesFor('workspace')) {
            if (class_exists(Workspace::class)) {
                $suggestions->suggestValues(Workspace::pluck('slug')->toArray());
            }
        }

        if ($input->mustSuggestOptionValuesFor('category')) {
            $suggestions->suggestValues(ConfigKey::distinct()->pluck('category')->toArray());
        }
    }
}
