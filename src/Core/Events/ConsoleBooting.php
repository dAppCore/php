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
 * Fired when the application runs in console/CLI context.
 *
 * Modules listen to this event to register Artisan commands for CLI operations
 * such as maintenance tasks, data processing, or administrative functions.
 *
 * ## When This Event Fires
 *
 * Fired when the application is invoked via `php artisan`, not during web
 * requests. This allows modules to only register commands when actually needed.
 *
 * ## Usage Example
 *
 * ```php
 * public static array $listens = [
 *     ConsoleBooting::class => 'onConsole',
 * ];
 *
 * public function onConsole(ConsoleBooting $event): void
 * {
 *     $event->command(ProcessOrdersCommand::class);
 *     $event->command(SyncInventoryCommand::class);
 * }
 * ```
 *
 *
 * @see QueueWorkerBooting For queue worker specific initialization
 */
class ConsoleBooting extends LifecycleEvent
{
    //
}
