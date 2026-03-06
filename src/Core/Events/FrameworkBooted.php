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
 * Fired after all service providers have booted.
 *
 * This event fires via Laravel's `$app->booted()` callback, after all service
 * providers have completed their `boot()` methods. Use this for late-stage
 * initialization that requires the full application context.
 *
 * ## When This Event Fires
 *
 * Fires after all service providers have booted, regardless of request type
 * (web, API, console, queue). This is one of the last events in the bootstrap
 * sequence.
 *
 * ## When to Use This Event
 *
 * Use FrameworkBooted sparingly. Most modules should prefer context-specific
 * events that only fire when relevant:
 *
 * - **WebRoutesRegistering** - Web routes only
 * - **AdminPanelBooting** - Admin requests only
 * - **ApiRoutesRegistering** - API requests only
 * - **ConsoleBooting** - CLI only
 *
 * Good use cases for FrameworkBooted:
 * - Cross-cutting concerns that apply to all contexts
 * - Initialization that depends on other modules being registered
 * - Late-binding configuration that needs full container state
 *
 * ## Usage Example
 *
 * ```php
 * public static array $listens = [
 *     FrameworkBooted::class => 'onBooted',
 * ];
 *
 * public function onBooted(FrameworkBooted $event): void
 * {
 *     // Late-stage initialization
 * }
 * ```
 */
class FrameworkBooted extends LifecycleEvent
{
    //
}
