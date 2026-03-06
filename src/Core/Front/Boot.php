<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Front;

use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\AggregateServiceProvider;

/**
 * Core front-end module - I/O translation layer.
 *
 * Six frontages bundled in the framework, each translating a transport protocol:
 *   Web        - HTTP → HTML (public marketing)
 *   Client     - HTTP → HTML (namespace owner dashboard)
 *   Admin      - HTTP → HTML (backend admin dashboard)
 *   Cli        - Artisan commands (console context)
 *   Stdio      - stdin/stdout (CLI pipes, MCP stdio)
 *   Components - View namespaces (shared across HTTP frontages)
 *
 * Additional frontages provided by their packages (auto-discovered):
 *   Api        - HTTP → JSON (REST API)           — php-api
 *   Mcp        - HTTP → JSON-RPC (MCP protocol)   — php-mcp
 */
class Boot extends AggregateServiceProvider
{
    protected $providers = [
        Web\Boot::class,
        Client\Boot::class,
        Admin\Boot::class,
        Cli\Boot::class,
        Stdio\Boot::class,
        Components\Boot::class,
    ];

    /**
     * Configure HTTP middleware - delegates to each HTTP frontage.
     * Stdio has no HTTP middleware (different transport).
     */
    public static function middleware(Middleware $middleware): void
    {
        Web\Boot::middleware($middleware);
        Client\Boot::middleware($middleware);
        Admin\Boot::middleware($middleware);

        // API and MCP groups — inlined because middleware() runs during
        // Application::configure(), before package providers load.
        // Packages add their own aliases during boot via lifecycle events.
        $middleware->group('api', [
            \Illuminate\Routing\Middleware\ThrottleRequests::class.':api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);
        $middleware->group('mcp', [
            \Illuminate\Routing\Middleware\ThrottleRequests::class.':api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);
    }
}
