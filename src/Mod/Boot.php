<?php

declare(strict_types=1);

namespace Core\Mod;

use Illuminate\Support\ServiceProvider;

/**
 * Mod Module Aggregator.
 *
 * Registers all feature modules. Each module has its own Boot.php
 * that handles routes, views, migrations, and service bindings.
 */
class Boot extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Child modules register themselves via config/app.php
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Child modules bootstrap themselves
    }
}
