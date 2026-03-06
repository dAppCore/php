<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core;

use Core\Events\EventAuditLog;
use Core\Events\ListenerProfiler;
use Illuminate\Support\ServiceProvider;

/**
 * Wraps a module method as a lazy-loading event listener.
 *
 * LazyModuleListener is the key to the framework's lazy loading strategy. Instead of
 * instantiating all modules at boot time, modules are only created when their
 * registered events actually fire. This significantly reduces memory usage and
 * speeds up application bootstrap for requests that don't use all modules.
 *
 * ## How Lazy Loading Works
 *
 * 1. During registration, a LazyModuleListener wraps each module class name and method
 * 2. The listener is registered with Laravel's event system
 * 3. When an event fires, `__invoke()` is called
 * 4. The module is instantiated via Laravel's container (first time only)
 * 5. The specified method is called with the event object
 *
 * ## ServiceProvider Support
 *
 * If the module class extends `ServiceProvider`, it's instantiated using
 * `$app->resolveProvider()` to ensure proper `$app` injection. Plain classes
 * use standard container resolution via `$app->make()`.
 *
 * ## Instance Caching
 *
 * Once instantiated, the module instance is cached for the lifetime of the
 * LazyModuleListener. This means if the same module listens to multiple events,
 * it will be instantiated once per event type.
 *
 * ## Audit Logging
 *
 * All event handling is tracked via EventAuditLog when enabled. This records:
 * - Event class name
 * - Handler module class name
 * - Execution duration
 * - Success/failure status
 *
 * ## Usage Example
 *
 * ```php
 * // Typically used by ModuleRegistry, but can be used directly:
 * Event::listen(
 *     AdminPanelBooting::class,
 *     new LazyModuleListener(Commerce\Boot::class, 'registerAdmin')
 * );
 * ```
 *
 *
 * @see ModuleRegistry For the automatic registration system
 * @see EventAuditLog For execution monitoring
 */
class LazyModuleListener
{
    /**
     * Cached module instance (created on first event).
     */
    private ?object $instance = null;

    /**
     * Create a new lazy module listener.
     *
     * @param  string  $moduleClass  Fully qualified class name of the module Boot class
     * @param  string  $method  Method name to call when the event fires
     */
    public function __construct(
        private string $moduleClass,
        private string $method
    ) {}

    /**
     * Handle the event by instantiating the module and calling its method.
     *
     * This is the callable interface for Laravel's event dispatcher. The module
     * is instantiated on first call and cached for subsequent events.
     *
     * Records execution timing and success/failure to EventAuditLog when enabled.
     * Profiles execution time and memory usage via ListenerProfiler when enabled.
     * Any exceptions thrown by the handler are re-thrown after logging.
     *
     * @param  object  $event  The lifecycle event instance
     *
     * @throws \Throwable Re-throws any exception from the module handler
     */
    public function __invoke(object $event): void
    {
        $eventClass = $event::class;

        EventAuditLog::recordStart($eventClass, $this->moduleClass);
        $profilerContext = ListenerProfiler::start($eventClass, $this->moduleClass, $this->method);

        try {
            $module = $this->resolveModule();
            $module->{$this->method}($event);
            EventAuditLog::recordSuccess($eventClass, $this->moduleClass);
        } catch (\Throwable $e) {
            EventAuditLog::recordFailure($eventClass, $this->moduleClass, $e);
            throw $e;
        } finally {
            ListenerProfiler::stop($profilerContext);
        }
    }

    /**
     * Alias for __invoke for explicit calls.
     */
    public function handle(object $event): void
    {
        $this->__invoke($event);
    }

    /**
     * Resolve the module instance.
     *
     * ServiceProviders are resolved via resolveProvider() to get proper $app injection.
     * Plain classes are resolved via make().
     */
    private function resolveModule(): object
    {
        if ($this->instance !== null) {
            return $this->instance;
        }

        $app = app();

        // Check if this is a ServiceProvider
        if (is_subclass_of($this->moduleClass, ServiceProvider::class)) {
            // Use resolveProvider for ServiceProviders - handles $app injection
            $this->instance = $app->resolveProvider($this->moduleClass);
        } else {
            // Plain class - just make it
            $this->instance = $app->make($this->moduleClass);
        }

        return $this->instance;
    }

    /**
     * Get the module class this listener wraps.
     */
    public function getModuleClass(): string
    {
        return $this->moduleClass;
    }

    /**
     * Get the method this listener will call.
     */
    public function getMethod(): string
    {
        return $this->method;
    }
}
