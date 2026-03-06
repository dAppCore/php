<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Search;

use Core\Search\Analytics\SearchAnalytics;
use Core\Search\Suggestions\SearchSuggestions;
use Illuminate\Support\ServiceProvider;

/**
 * Search module service provider.
 *
 * Unified search across all system components.
 */
class Boot extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config.php', 'search');

        $this->app->singleton(Unified::class);
        $this->app->singleton(SearchAnalytics::class);
        $this->app->singleton(SearchSuggestions::class);

        $this->registerBackwardCompatAliases();
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadMigrations();
        $this->publishConfig();
    }

    /**
     * Load migrations for search analytics.
     */
    protected function loadMigrations(): void
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/Analytics/migrations');
        }
    }

    /**
     * Publish configuration.
     */
    protected function publishConfig(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/config.php' => config_path('search.php'),
            ], 'search-config');
        }
    }

    /**
     * Register backward compatibility aliases for old namespaces.
     */
    protected function registerBackwardCompatAliases(): void
    {
        if (! class_exists(\App\Services\Search\UnifiedSearchService::class)) {
            class_alias(Unified::class, \App\Services\Search\UnifiedSearchService::class);
        }
    }
}
