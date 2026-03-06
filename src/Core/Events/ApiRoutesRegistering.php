<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Events;

/**
 * Fired when REST API routes are being registered.
 *
 * Modules listen to this event to register their REST API endpoints for
 * programmatic access by external applications, mobile apps, or SPAs.
 *
 * ## When This Event Fires
 *
 * Fired by `LifecycleEventProvider::fireApiRoutes()` when the API frontage
 * initializes, typically for requests to `/api/*` routes.
 *
 * ## Middleware and Prefix
 *
 * Routes registered through this event are automatically:
 * - Wrapped with the 'api' middleware group (typically stateless, rate limiting)
 * - Prefixed with `/api`
 *
 * ## Usage Example
 *
 * ```php
 * public static array $listens = [
 *     ApiRoutesRegistering::class => 'onApi',
 * ];
 *
 * public function onApi(ApiRoutesRegistering $event): void
 * {
 *     $event->routes(fn () => require __DIR__.'/Routes/api.php');
 * }
 * ```
 *
 * Note: API routes typically don't need views or Livewire components, but
 * all LifecycleEvent methods are available if needed.
 *
 *
 * @see WebRoutesRegistering For web routes with session state
 */
class ApiRoutesRegistering extends LifecycleEvent
{
    //
}
