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
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\AggregateServiceProvider;

/**
 * Core front-end module - I/O translation layer.
 *
 * Five frontages bundled in the framework, each translating a transport protocol:
 *   Web        - HTTP → HTML (public marketing)
 *   Admin      - HTTP → HTML (backend admin dashboard)
 *   Cli        - Artisan commands (console context)
 *   Stdio      - stdin/stdout (CLI pipes, MCP stdio)
 *   Components - View namespaces (shared across HTTP frontages)
 *
 * Additional frontages provided by their packages (auto-discovered):
 *   Client     - HTTP → HTML (namespace owner dashboard) — php-client
 *   Api        - HTTP → JSON (REST API)                  — php-api
 *   Mcp        - HTTP → JSON-RPC (MCP protocol)           — php-mcp
 *
 * Client\Boot lived here until it was extracted to the standalone
 * php-client package (see git history: "refactor: extract Service +
 * Client to standalone packages"). The extraction deleted
 * src/Core/Front/Client/ but left this class still hard-referencing
 * Client\Boot in both $providers and middleware() — a class that no
 * longer exists in this repo — which fataled Application::configure()
 * for every consuming app, not just ones missing php-client.
 */
class Boot extends AggregateServiceProvider
{
    protected $providers = [
        Web\Boot::class,
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
        Admin\Boot::middleware($middleware);

        // API and MCP groups — inlined because middleware() runs during
        // Application::configure(), before package providers load.
        // Packages add their own aliases during boot via lifecycle events.
        $middleware->group('api', [
            ThrottleRequests::class.':api',
            SubstituteBindings::class,
        ]);
        $middleware->group('mcp', [
            ThrottleRequests::class.':api',
            SubstituteBindings::class,
        ]);
    }
}
