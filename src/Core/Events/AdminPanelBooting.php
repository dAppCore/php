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
 * Fired when the admin panel is being bootstrapped.
 *
 * Modules listen to this event to register admin-specific resources including
 * routes, views, Livewire components, and translations for the admin dashboard.
 *
 * ## When This Event Fires
 *
 * Fired by `LifecycleEventProvider::fireAdminBooting()` only for requests
 * to admin routes. Not fired for public pages, API calls, or client dashboard.
 *
 * ## Middleware
 *
 * Routes registered through this event are automatically wrapped with the 'admin'
 * middleware group, which typically includes authentication, admin authorization, etc.
 *
 * ## Navigation Items
 *
 * For admin navigation, consider implementing `AdminMenuProvider` interface
 * for more control over menu items including permissions, entitlements, and groups.
 *
 * ## Usage Example
 *
 * ```php
 * public static array $listens = [
 *     AdminPanelBooting::class => 'onAdmin',
 * ];
 *
 * public function onAdmin(AdminPanelBooting $event): void
 * {
 *     $event->views('commerce', __DIR__.'/Views/Admin');
 *     $event->translations('commerce', __DIR__.'/Lang');
 *     $event->livewire('commerce-dashboard', DashboardComponent::class);
 *     $event->routes(fn () => require __DIR__.'/Routes/admin.php');
 * }
 * ```
 *
 *
 * @see AdminMenuProvider For navigation registration
 * @see WebRoutesRegistering For public web routes
 */
class AdminPanelBooting extends LifecycleEvent
{
    //
}
