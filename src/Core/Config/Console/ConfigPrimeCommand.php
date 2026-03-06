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
use Illuminate\Console\Command;

class ConfigPrimeCommand extends Command
{
    protected $signature = 'config:prime
                            {workspace? : Workspace slug to prime (omit for all)}
                            {--system : Prime system config only}';

    protected $description = 'Prime the config cache (compute resolution, store in cache)';

    public function handle(ConfigService $config): int
    {
        $workspaceSlug = $this->argument('workspace');
        $systemOnly = $this->option('system');

        if ($systemOnly) {
            $this->info('Priming system config cache...');
            $config->prime(null);
            $this->info('System config cached.');

            return self::SUCCESS;
        }

        if ($workspaceSlug) {
            if (! class_exists(\Core\Tenant\Models\Workspace::class)) {
                $this->error('Tenant module not installed. Cannot prime workspace config.');

                return self::FAILURE;
            }

            $workspace = \Core\Tenant\Models\Workspace::where('slug', $workspaceSlug)->first();

            if (! $workspace) {
                $this->error("Workspace not found: {$workspaceSlug}");

                return self::FAILURE;
            }

            $this->info("Priming config cache for workspace: {$workspace->slug}");
            $config->prime($workspace);
            $this->info('Workspace config cached.');

            return self::SUCCESS;
        }

        $this->info('Priming config cache for all workspaces...');

        if (! class_exists(\Core\Tenant\Models\Workspace::class)) {
            $this->warn('Tenant module not installed. Only priming system config.');
            $config->prime(null);
            $this->info('System config cached.');

            return self::SUCCESS;
        }

        $this->withProgressBar(\Core\Tenant\Models\Workspace::all(), function ($workspace) use ($config) {
            $config->prime($workspace);
        });

        $this->newLine();
        $this->info('All config caches primed.');

        return self::SUCCESS;
    }
}
