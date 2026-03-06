<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Front\Cli;

use Core\Events\ConsoleBooting;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * CLI frontage - console/artisan context.
 *
 * Fires ConsoleBooting event and processes module requests for:
 * - Artisan commands
 * - Translations
 * - Middleware aliases
 * - Policies
 * - Blade component paths
 */
class Boot extends ServiceProvider
{
    public function boot(): void
    {
        // Only fire for CLI context
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->fireConsoleBooting();
    }

    protected function fireConsoleBooting(): void
    {
        $event = new ConsoleBooting;
        event($event);

        // Process commands
        if (! empty($event->commandRequests())) {
            $this->commands($event->commandRequests());
        }

        // Process translations
        foreach ($event->translationRequests() as [$namespace, $path]) {
            if (is_dir($path)) {
                $this->loadTranslationsFrom($path, $namespace);
            }
        }

        // Process middleware aliases
        $router = $this->app->make(\Illuminate\Routing\Router::class);
        foreach ($event->middlewareRequests() as [$alias, $class]) {
            $router->aliasMiddleware($alias, $class);
        }

        // Process policies
        foreach ($event->policyRequests() as [$model, $policy]) {
            Gate::policy($model, $policy);
        }

        // Process blade component paths
        foreach ($event->bladeComponentRequests() as [$path, $namespace]) {
            if (is_dir($path)) {
                Blade::anonymousComponentPath($path, $namespace);
            }
        }
    }
}
