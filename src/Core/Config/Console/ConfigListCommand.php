<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Config\Console;

use Core\Config\ConfigService;
use Core\Config\Models\ConfigKey;
use Illuminate\Console\Command;

class ConfigListCommand extends Command
{
    protected $signature = 'config:list
                            {--workspace= : Show config for specific workspace slug}
                            {--category= : Filter by category}
                            {--configured : Only show configured keys}';

    protected $description = 'List config keys and their resolved values';

    public function handle(ConfigService $config): int
    {
        $workspaceSlug = $this->option('workspace');
        $category = $this->option('category');
        $configuredOnly = $this->option('configured');

        $workspace = null;

        if ($workspaceSlug) {
            if (! class_exists(\Core\Tenant\Models\Workspace::class)) {
                $this->error('Tenant module not installed. Cannot filter by workspace.');

                return self::FAILURE;
            }

            $workspace = \Core\Tenant\Models\Workspace::where('slug', $workspaceSlug)->first();

            if (! $workspace) {
                $this->error("Workspace not found: {$workspaceSlug}");

                return self::FAILURE;
            }

            $this->info("Config for workspace: {$workspace->slug}");
        } else {
            $this->info('System config:');
        }

        $this->newLine();

        $query = ConfigKey::query();

        if ($category) {
            $query->where('category', $category);
        }

        $keys = $query->orderBy('category')->orderBy('code')->get();

        $rows = [];

        foreach ($keys as $key) {
            $result = $config->resolve($key->code, $workspace);

            if ($configuredOnly && ! $result->isConfigured()) {
                continue;
            }

            $value = $result->get();
            $displayValue = match (true) {
                is_null($value) => '<fg=gray>null</>',
                is_bool($value) => $value ? '<fg=green>true</>' : '<fg=red>false</>',
                is_array($value) => '<fg=cyan>[array]</>',
                is_string($value) && strlen($value) > 40 => substr($value, 0, 37).'...',
                default => (string) $value,
            };

            $rows[] = [
                $key->code,
                $key->category,
                $key->type->value,
                $displayValue,
                $result->isLocked() ? '<fg=yellow>LOCKED</>' : '',
                $result->resolvedFrom?->value ?? '<fg=gray>default</>',
            ];
        }

        if (empty($rows)) {
            $this->warn('No config keys found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Key', 'Category', 'Type', 'Value', 'Status', 'Source'],
            $rows
        );

        return self::SUCCESS;
    }
}
