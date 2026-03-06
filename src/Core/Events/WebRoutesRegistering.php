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
 * Fired when public web routes are being registered.
 *
 * Modules listen to this event to register public-facing web routes such as
 * marketing pages, product listings, or any routes accessible without authentication.
 *
 * ## When This Event Fires
 *
 * Fired by `LifecycleEventProvider::fireWebRoutes()` when the web frontage
 * initializes, typically early in the request lifecycle for web requests.
 *
 * ## Middleware
 *
 * Routes registered through this event are automatically wrapped with the 'web'
 * middleware group, which typically includes session handling, CSRF protection, etc.
 *
 * ## Usage Example
 *
 * ```php
 * // In your module's Boot class:
 * public static array $listens = [
 *     WebRoutesRegistering::class => 'onWebRoutes',
 * ];
 *
 * public function onWebRoutes(WebRoutesRegistering $event): void
 * {
 *     $event->views('marketing', __DIR__.'/Views');
 *     $event->routes(fn () => require __DIR__.'/Routes/web.php');
 * }
 * ```
 *
 * ## When to Use Other Events
 *
 * - **AdminPanelBooting** - For admin dashboard routes
 * - **ClientRoutesRegistering** - For authenticated customer/namespace routes
 * - **ApiRoutesRegistering** - For REST API endpoints
 *
 *
 * @see AdminPanelBooting For admin routes
 * @see ClientRoutesRegistering For client dashboard routes
 */
class WebRoutesRegistering extends LifecycleEvent
{
    //
}
