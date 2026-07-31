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
 * Client\Boot lived here until it was extracted to the standalone client
 * package. The extraction deleted src/Core/Front/Client/ but left this
 * class hard-referencing Client\Boot in both $providers and middleware(),
 * so an application without that package fataled during
 * Application::configure() on a class that is no longer here.
 *
 * It cannot simply be dropped either. The client package declares no
 * providers for Laravel's package discovery — only "dont-discover" — so
 * this list is the ONLY thing that registers the Client frontage.
 * Removing the line stops the fatal and silently takes the dashboard with
 * it, which is the worse failure of the two: an application that boots
 * fine and has quietly lost a surface.
 *
 * So it is registered when it is installed, and skipped when it is not.
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

    public function register(): void
    {
        if (class_exists(Client\Boot::class)) {
            $this->providers[] = Client\Boot::class;
        }

        parent::register();
    }

    /**
     * Configure HTTP middleware - delegates to each HTTP frontage.
     * Stdio has no HTTP middleware (different transport).
     */
    public static function middleware(Middleware $middleware): void
    {
        Web\Boot::middleware($middleware);
        Admin\Boot::middleware($middleware);

        // Same reason as the provider list above: present when the client
        // package is installed, absent when it is not, and its middleware
        // group has to follow it either way.
        if (class_exists(Client\Boot::class)) {
            Client\Boot::middleware($middleware);
        }

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
