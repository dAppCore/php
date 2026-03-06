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
 * Fired when client dashboard routes are being registered.
 *
 * Modules listen to this event to register routes for namespace owners -
 * authenticated SaaS customers who manage their own space within the platform.
 *
 * ## When This Event Fires
 *
 * Fired by `LifecycleEventProvider::fireClientRoutes()` when the client
 * frontage initializes, typically for requests to client dashboard routes.
 *
 * ## Middleware
 *
 * Routes registered through this event are automatically wrapped with the 'client'
 * middleware group, which typically includes authentication and workspace context.
 *
 * ## Typical Use Cases
 *
 * - Bio/link page editors
 * - User settings and preferences
 * - Analytics dashboards
 * - Content management
 * - Billing and subscription management
 *
 * ## Usage Example
 *
 * ```php
 * public static array $listens = [
 *     ClientRoutesRegistering::class => 'onClient',
 * ];
 *
 * public function onClient(ClientRoutesRegistering $event): void
 * {
 *     $event->views('bio', __DIR__.'/Views/Client');
 *     $event->livewire('bio-editor', BioEditorComponent::class);
 *     $event->routes(fn () => require __DIR__.'/Routes/client.php');
 * }
 * ```
 *
 *
 * @see AdminPanelBooting For admin/staff routes
 * @see WebRoutesRegistering For public-facing routes
 */
class ClientRoutesRegistering extends LifecycleEvent
{
    //
}
