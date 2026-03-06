<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Config;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/**
 * Hierarchical Configuration Module Service Provider.
 *
 * Provides workspace-aware config with inheritance and FINAL locks.
 *
 * Usage:
 *   $config = app(ConfigService::class);
 *   $value = $config->get('cdn.bunny.api_key', $workspace);
 *   if ($config->isConfigured('cdn.bunny', $workspace)) { ... }
 *
 * ## Import/Export
 *
 * Export config to JSON or YAML for backup, migration, or sharing:
 *
 * ```php
 * $exporter = app(ConfigExporter::class);
 * $json = $exporter->exportJson($workspace);
 * $result = $exporter->importJson($json, $workspace);
 * ```
 *
 * CLI commands:
 * - `config:export config.json` - Export to file
 * - `config:import config.json` - Import from file
 *
 * ## Versioning & Rollback
 *
 * Create snapshots and rollback to previous states:
 *
 * ```php
 * $versioning = app(ConfigVersioning::class);
 * $version = $versioning->createVersion($workspace, 'Before migration');
 * $versioning->rollback($version->id, $workspace);
 * ```
 *
 * CLI commands:
 * - `config:version list` - List all versions
 * - `config:version create "Label"` - Create snapshot
 * - `config:version rollback 123` - Rollback to version
 * - `config:version compare 122 123` - Compare versions
 *
 * ## Configuration
 *
 * | Key | Type | Default | Description |
 * |-----|------|---------|-------------|
 * | `core.config.max_versions` | int | 50 | Max versions per scope |
 */
class Boot extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(ConfigResolver::class);

        $this->app->singleton(ConfigService::class, function ($app) {
            return new ConfigService($app->make(ConfigResolver::class));
        });

        // Alias for convenience
        $this->app->alias(ConfigService::class, 'config.service');

        // Register exporter service
        $this->app->singleton(ConfigExporter::class, function ($app) {
            return new ConfigExporter($app->make(ConfigService::class));
        });

        // Register versioning service
        $this->app->singleton(ConfigVersioning::class, function ($app) {
            return new ConfigVersioning(
                $app->make(ConfigService::class),
                $app->make(ConfigExporter::class)
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/Migrations');

        // Load views
        $this->loadViewsFrom(__DIR__.'/View/Blade', 'core.config');

        // Load routes
        $this->loadRoutesFrom(__DIR__.'/Routes/admin.php');

        // Register Livewire components
        Livewire::component('app.core.config.view.modal.admin.workspace-config', View\Modal\Admin\WorkspaceConfig::class);
        Livewire::component('app.core.config.view.modal.admin.config-panel', View\Modal\Admin\ConfigPanel::class);

        // Register console commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\ConfigPrimeCommand::class,
                Console\ConfigListCommand::class,
                Console\ConfigExportCommand::class,
                Console\ConfigImportCommand::class,
                Console\ConfigVersionCommand::class,
            ]);
        }

        // Boot key registry after app is ready (deferred to avoid DB during boot)
        // Config resolver now uses lazy loading - no boot-time initialization needed
    }

    /**
     * Check if database is unavailable (migration context).
     */
    protected function isDbUnavailable(): bool
    {
        // Check if we're running migrate or db commands
        $command = $_SERVER['argv'][1] ?? '';

        return in_array($command, ['migrate', 'migrate:fresh', 'migrate:reset', 'db:seed', 'db:wipe']);
    }
}
