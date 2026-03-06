<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core;

use Core\Input\Input;

/**
 * Application initialisation - the true entry point.
 *
 * Use this in public/index.php:
 *
 *     require __DIR__.'/../vendor/autoload.php';
 *     Core\Init::handle();
 *
 * This replaces Laravel's bootstrap/app.php pattern with
 * explicit provider loading via Core\Boot.
 *
 * The Input::capture() method provides a WAF layer that sanitises
 * all input ($_GET, $_POST) before Laravel sees it.
 */
class Init
{
    /**
     * Handle the incoming request.
     */
    public static function handle(): void
    {
        // Maintenance mode check (before anything else)
        $maintenance = dirname(__DIR__, 4).'/storage/framework/maintenance.php';
        if (file_exists($maintenance)) {
            require $maintenance;
        }

        // Capture and filter input - WAF layer
        // This sanitises $_GET and $_POST before creating the request
        $request = Input::capture();

        // Hand clean request to Laravel
        // Use App\Boot if it exists (app customizations), otherwise Core\Boot
        self::boot()::app()->handleRequest($request);
    }

    /**
     * Handle for testing - returns response instead of sending.
     */
    public static function handleForTesting(): mixed
    {
        $request = Input::capture();

        return self::boot()::app()->handle($request);
    }

    /**
     * Get the Boot class to use.
     *
     * Prefers App\Boot if it exists, allowing apps to customise
     * providers, middleware, and exception handling.
     */
    protected static function boot(): string
    {
        return class_exists('App\\Boot') ? 'App\\Boot' : Boot::class;
    }
}
