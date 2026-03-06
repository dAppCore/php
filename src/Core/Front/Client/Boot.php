<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Front\Client;

use Core\Headers\SecurityHeaders;
use Core\LifecycleEventProvider;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

/**
 * Client frontage - namespace owner dashboard.
 *
 * For SaaS customers managing their namespace (personal workspace).
 * Not the full Hub/Admin - just YOUR space on the internet.
 *
 * Hierarchy:
 * - Core/Front/Web    = Public (anonymous, read-only)
 * - Core/Front/Client = SaaS customer (authenticated, namespace owner)
 * - Core/Front/Admin  = Backend admin (privileged)
 * - Core/Hub          = SaaS operator (Host.uk.com control plane)
 *
 * A namespace is tied to a URI/handle (lt.hn/you, you.lthn).
 * A workspace (org) can manage multiple namespaces.
 * A personal workspace IS your namespace.
 */
class Boot extends ServiceProvider
{
    /**
     * Configure client middleware group.
     */
    public static function middleware(Middleware $middleware): void
    {
        $middleware->group('client', [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            SecurityHeaders::class,
            'auth',
        ]);
    }

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Register client:: namespace for client dashboard components
        $this->loadViewsFrom(__DIR__.'/Blade', 'client');
        Blade::anonymousComponentPath(__DIR__.'/Blade', 'client');

        // Fire ClientRoutesRegistering event for lazy-loaded modules
        LifecycleEventProvider::fireClientRoutes();
    }
}
