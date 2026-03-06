<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

/**
 * Application bootstrap - configures Laravel with Core framework patterns.
 *
 * Consuming apps use Core\Init::handle() as their entry point.
 * This class configures providers, middleware, and exception handling.
 *
 * Provider loading order matters:
 * 1. LifecycleEventProvider - wires lazy module listeners
 * 2. Mod\Boot - domain-scoped event listeners (before frontages)
 * 3. Front\Boot - fires lifecycle events that modules respond to
 */
class Boot
{
    /**
     * Service providers loaded by the framework.
     *
     * Consuming apps can extend this by creating their own Boot class
     * that merges additional providers.
     */
    public static array $providers = [
        // Lifecycle events - must load first to wire lazy listeners
        \Core\LifecycleEventProvider::class,

        // Websites - domain-scoped, must wire before frontages fire events
        \Core\Website\Boot::class,

        // Core frontages - fire lifecycle events
        \Core\Front\Boot::class,

        // Base modules (from core-php package)
        \Core\Mod\Boot::class,
    ];

    /**
     * Create and configure the application.
     */
    public static function app(): Application
    {
        return Application::configure(basePath: self::basePath())
            ->withProviders(self::$providers)
            ->withMiddleware(function (Middleware $middleware): void {
                // Session middleware priority
                $middleware->priority([
                    \Illuminate\Session\Middleware\StartSession::class,
                ]);

                $middleware->redirectGuestsTo('/login');
                $middleware->redirectUsersTo('/hub');

                // Front module configures middleware groups (web, admin, api, mcp)
                Front\Boot::middleware($middleware);
            })
            ->withExceptions(function (Exceptions $exceptions): void {
                // Clean exception handling for open-source
                // Apps can add Sentry, custom error pages, etc.
            })->create();
    }

    /**
     * Get the application base path.
     *
     * Works whether Core is in vendor/ or packages/ (monorepo).
     */
    protected static function basePath(): string
    {
        // Check for monorepo structure (packages/core-php/src/Core/Boot.php)
        // The monorepo root has app/ directory while the package root doesn't
        $monorepoBase = dirname(__DIR__, 4);
        if (file_exists($monorepoBase.'/composer.json') && is_dir($monorepoBase.'/app')) {
            return $monorepoBase;
        }

        // Standard vendor structure (vendor/*/core/src/Core/Boot.php)
        return dirname(__DIR__, 5);
    }
}
