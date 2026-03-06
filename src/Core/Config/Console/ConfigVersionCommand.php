<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Config\Console;

use Core\Config\ConfigVersioning;
use Core\Config\Models\ConfigVersion;
use Illuminate\Console\Command;
use Symfony\Component\Console\Completion\CompletionInput;

/**
 * Manage config versions.
 *
 * Usage:
 *   php artisan config:version list
 *   php artisan config:version create "Before deployment"
 *   php artisan config:version show 123
 *   php artisan config:version rollback 123
 *   php artisan config:version compare 122 123
 *   php artisan config:version diff 123
 */
class ConfigVersionCommand extends Command
{
    protected $signature = 'config:version
                            {action : Action to perform (list, create, show, rollback, compare, diff, delete)}
                            {arg1? : First argument (version ID or label)}
                            {arg2? : Second argument (version ID for compare)}
                            {--workspace= : Workspace slug for version operations}
                            {--limit=20 : Maximum versions to list}
                            {--no-backup : Skip backup when rolling back}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Manage config versions (snapshots for rollback)';

    public function handle(ConfigVersioning $versioning): int
    {
        $action = $this->argument('action');
        $arg1 = $this->argument('arg1');
        $arg2 = $this->argument('arg2');
        $workspaceSlug = $this->option('workspace');

        // Resolve workspace
        $workspace = null;
        if ($workspaceSlug) {
            if (! class_exists(\Core\Tenant\Models\Workspace::class)) {
                $this->components->error('Tenant module not installed. Cannot manage workspace versions.');

                return self::FAILURE;
            }

            $workspace = \Core\Tenant\Models\Workspace::where('slug', $workspaceSlug)->first();

            if (! $workspace) {
                $this->components->error("Workspace not found: {$workspaceSlug}");

                return self::FAILURE;
            }
        }

        return match ($action) {
            'list' => $this->listVersions($versioning, $workspace),
            'create' => $this->createVersion($versioning, $workspace, $arg1),
            'show' => $this->showVersion($versioning, $arg1),
            'rollback' => $this->rollbackVersion($versioning, $workspace, $arg1),
            'compare' => $this->compareVersions($versioning, $workspace, $arg1, $arg2),
            'diff' => $this->diffWithCurrent($versioning, $workspace, $arg1),
            'delete' => $this->deleteVersion($versioning, $arg1),
            default => $this->invalidAction($action),
        };
    }

    /**
     * List versions.
     */
    protected function listVersions(ConfigVersioning $versioning, ?object $workspace): int
    {
        $limit = (int) $this->option('limit');
        $versions = $versioning->getVersions($workspace, $limit);

        $scope = $workspace ? "workspace: {$workspace->slug}" : 'system';
        $this->components->info("Config versions for {$scope}:");

        if ($versions->isEmpty()) {
            $this->components->warn('No versions found.');

            return self::SUCCESS;
        }

        $rows = $versions->map(fn (ConfigVersion $v) => [
            $v->id,
            $v->label,
            $v->author ?? '<fg=gray>-</>',
            $v->created_at->format('Y-m-d H:i:s'),
            $v->created_at->diffForHumans(),
        ])->toArray();

        $this->table(
            ['ID', 'Label', 'Author', 'Created', 'Age'],
            $rows
        );

        return self::SUCCESS;
    }

    /**
     * Create a new version.
     */
    protected function createVersion(ConfigVersioning $versioning, ?object $workspace, ?string $label): int
    {
        $label = $label ?? 'Manual snapshot';

        $version = null;
        $this->components->task("Creating version: {$label}", function () use ($versioning, $workspace, $label, &$version) {
            $version = $versioning->createVersion($workspace, $label);
        });

        $this->components->info("Version created: ID {$version->id}");

        return self::SUCCESS;
    }

    /**
     * Show version details.
     */
    protected function showVersion(ConfigVersioning $versioning, ?string $versionId): int
    {
        if ($versionId === null) {
            $this->components->error('Version ID required.');

            return self::FAILURE;
        }

        $version = $versioning->getVersion((int) $versionId);

        if ($version === null) {
            $this->components->error("Version not found: {$versionId}");

            return self::FAILURE;
        }

        $this->components->info("Version #{$version->id}: {$version->label}");
        $this->components->twoColumnDetail('Created', $version->created_at->format('Y-m-d H:i:s'));
        $this->components->twoColumnDetail('Author', $version->author ?? '-');
        $this->components->twoColumnDetail('Workspace ID', $version->workspace_id ?? 'system');

        $values = $version->getValues();
        $this->newLine();
        $this->components->info('Values ('.count($values).' items):');

        $rows = array_map(function ($v) {
            $displayValue = match (true) {
                is_array($v['value']) => '<fg=cyan>[array]</>',
                is_null($v['value']) => '<fg=gray>null</>',
                is_bool($v['value']) => $v['value'] ? '<fg=green>true</>' : '<fg=red>false</>',
                is_string($v['value']) && strlen($v['value']) > 40 => substr($v['value'], 0, 37).'...',
                default => (string) $v['value'],
            };

            return [
                $v['key'],
                $displayValue,
                $v['locked'] ?? false ? '<fg=yellow>LOCKED</>' : '',
            ];
        }, $values);

        $this->table(['Key', 'Value', 'Status'], $rows);

        return self::SUCCESS;
    }

    /**
     * Rollback to a version.
     */
    protected function rollbackVersion(ConfigVersioning $versioning, ?object $workspace, ?string $versionId): int
    {
        if ($versionId === null) {
            $this->components->error('Version ID required.');

            return self::FAILURE;
        }

        $version = $versioning->getVersion((int) $versionId);

        if ($version === null) {
            $this->components->error("Version not found: {$versionId}");

            return self::FAILURE;
        }

        $scope = $workspace ? "workspace: {$workspace->slug}" : 'system';

        if (! $this->option('force')) {
            $this->components->warn("This will restore config to version #{$version->id}: {$version->label}");
            $this->components->warn("Scope: {$scope}");

            if (! $this->confirm('Are you sure you want to rollback?')) {
                $this->components->info('Rollback cancelled.');

                return self::SUCCESS;
            }
        }

        $createBackup = ! $this->option('no-backup');
        $result = null;

        $this->components->task('Rolling back config', function () use ($versioning, $workspace, $versionId, $createBackup, &$result) {
            $result = $versioning->rollback((int) $versionId, $workspace, $createBackup);
        });

        $this->newLine();
        $this->components->info("Rollback complete: {$result->getSummary()}");

        if ($createBackup) {
            $this->components->info('A backup version was created before rollback.');
        }

        return self::SUCCESS;
    }

    /**
     * Compare two versions.
     */
    protected function compareVersions(ConfigVersioning $versioning, ?object $workspace, ?string $oldId, ?string $newId): int
    {
        if ($oldId === null || $newId === null) {
            $this->components->error('Two version IDs required for comparison.');

            return self::FAILURE;
        }

        $diff = $versioning->compare($workspace, (int) $oldId, (int) $newId);

        $this->components->info("Comparing version #{$oldId} to #{$newId}:");
        $this->newLine();

        if ($diff->isEmpty()) {
            $this->components->info('No differences found.');

            return self::SUCCESS;
        }

        $this->displayDiff($diff);

        return self::SUCCESS;
    }

    /**
     * Compare version with current state.
     */
    protected function diffWithCurrent(ConfigVersioning $versioning, ?object $workspace, ?string $versionId): int
    {
        if ($versionId === null) {
            $this->components->error('Version ID required.');

            return self::FAILURE;
        }

        $diff = $versioning->compareWithCurrent($workspace, (int) $versionId);

        $this->components->info("Comparing version #{$versionId} to current state:");
        $this->newLine();

        if ($diff->isEmpty()) {
            $this->components->info('No differences found. Current state matches the version.');

            return self::SUCCESS;
        }

        $this->displayDiff($diff);

        return self::SUCCESS;
    }

    /**
     * Display a diff.
     */
    protected function displayDiff(\Core\Config\VersionDiff $diff): void
    {
        $this->components->info("Summary: {$diff->getSummary()}");
        $this->newLine();

        // Added
        if (count($diff->getAdded()) > 0) {
            $this->components->twoColumnDetail('<fg=green>Added</>', count($diff->getAdded()).' keys');
            foreach ($diff->getAdded() as $item) {
                $this->line("  <fg=green>+</> {$item['key']}");
            }
            $this->newLine();
        }

        // Removed
        if (count($diff->getRemoved()) > 0) {
            $this->components->twoColumnDetail('<fg=red>Removed</>', count($diff->getRemoved()).' keys');
            foreach ($diff->getRemoved() as $item) {
                $this->line("  <fg=red>-</> {$item['key']}");
            }
            $this->newLine();
        }

        // Changed
        if (count($diff->getChanged()) > 0) {
            $this->components->twoColumnDetail('<fg=yellow>Changed</>', count($diff->getChanged()).' keys');
            foreach ($diff->getChanged() as $item) {
                $oldDisplay = $this->formatValue($item['old']);
                $newDisplay = $this->formatValue($item['new']);
                $this->line("  <fg=yellow>~</> {$item['key']}");
                $this->line("    <fg=gray>old:</> {$oldDisplay}");
                $this->line("    <fg=gray>new:</> {$newDisplay}");
            }
            $this->newLine();
        }

        // Lock changes
        if (count($diff->getLockChanged()) > 0) {
            $this->components->twoColumnDetail('<fg=cyan>Lock Changed</>', count($diff->getLockChanged()).' keys');
            foreach ($diff->getLockChanged() as $item) {
                $oldLock = $item['old'] ? 'LOCKED' : 'unlocked';
                $newLock = $item['new'] ? 'LOCKED' : 'unlocked';
                $this->line("  <fg=cyan>*</> {$item['key']}: {$oldLock} -> {$newLock}");
            }
        }
    }

    /**
     * Format a value for display.
     */
    protected function formatValue(mixed $value): string
    {
        return match (true) {
            is_array($value) => '[array]',
            is_null($value) => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_string($value) && strlen($value) > 50 => '"'.substr($value, 0, 47).'..."',
            default => (string) $value,
        };
    }

    /**
     * Delete a version.
     */
    protected function deleteVersion(ConfigVersioning $versioning, ?string $versionId): int
    {
        if ($versionId === null) {
            $this->components->error('Version ID required.');

            return self::FAILURE;
        }

        $version = $versioning->getVersion((int) $versionId);

        if ($version === null) {
            $this->components->error("Version not found: {$versionId}");

            return self::FAILURE;
        }

        if (! $this->option('force')) {
            $this->components->warn("This will permanently delete version #{$version->id}: {$version->label}");

            if (! $this->confirm('Are you sure you want to delete this version?')) {
                $this->components->info('Delete cancelled.');

                return self::SUCCESS;
            }
        }

        $versioning->deleteVersion((int) $versionId);
        $this->components->info("Version #{$versionId} deleted.");

        return self::SUCCESS;
    }

    /**
     * Handle invalid action.
     */
    protected function invalidAction(string $action): int
    {
        $this->components->error("Invalid action: {$action}");
        $this->newLine();
        $this->components->info('Available actions:');
        $this->components->bulletList([
            'list     - List all versions',
            'create   - Create a new version snapshot',
            'show     - Show version details',
            'rollback - Restore config to a version',
            'compare  - Compare two versions',
            'diff     - Compare version with current state',
            'delete   - Delete a version',
        ]);

        return self::FAILURE;
    }

    /**
     * Get autocompletion suggestions.
     */
    public function complete(CompletionInput $input, \Symfony\Component\Console\Completion\CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor('action')) {
            $suggestions->suggestValues(['list', 'create', 'show', 'rollback', 'compare', 'diff', 'delete']);
        }

        if ($input->mustSuggestOptionValuesFor('workspace')) {
            if (class_exists(\Core\Tenant\Models\Workspace::class)) {
                $suggestions->suggestValues(\Core\Tenant\Models\Workspace::pluck('slug')->toArray());
            }
        }
    }
}
