<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Front\Stdio;

use Illuminate\Support\ServiceProvider;

/**
 * Stdio frontage - CLI/terminal stage.
 *
 * Handles stdin/stdout I/O for:
 *   - Artisan commands
 *   - MCP stdio transport (agents connecting via pipes)
 *   - Interactive CLI tools
 *
 * No HTTP middleware - this is a different transport entirely.
 */
class Boot extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Register console commands if running in console
        if ($this->app->runningInConsole()) {
            $this->commands([
                // Core CLI commands registered here
            ]);
        }
    }
}
