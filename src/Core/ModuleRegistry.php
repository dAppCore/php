<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core;

use Illuminate\Support\Facades\Event;

/**
 * Manages lazy module registration via Laravel's event system.
 *
 * The ModuleRegistry is the central coordinator for the event-driven module loading
 * system. It uses ModuleScanner to discover modules, then wires up LazyModuleListener
 * instances for each event-module pair.
 *
 * ## Registration Flow
 *
 * 1. `register()` is called with paths to scan (typically in a ServiceProvider)
 * 2. ModuleScanner discovers all Boot classes with `$listens` declarations
 * 3. For each event-listener pair, a LazyModuleListener is registered
 * 4. Listeners are sorted by priority (highest first) before registration
 * 5. When events fire, LazyModuleListener instantiates modules on-demand
 *
 * ## Priority System
 *
 * Listeners are sorted by priority before registration with Laravel's event system.
 * Higher priority values run first:
 *
 * - Priority 100: Runs first
 * - Priority 0: Default
 * - Priority -100: Runs last
 *
 * ## Usage Example
 *
 * ```php
 * // In a ServiceProvider's register() method:
 * $registry = new ModuleRegistry(new ModuleScanner());
 * $registry->register([
 *     app_path('Core'),
 *     app_path('Mod'),
 *     app_path('Website'),
 * ]);
 *
 * // Query registered modules:
 * $events = $registry->getEvents();
 * $modules = $registry->getModules();
 * $listeners = $registry->getListenersFor(WebRoutesRegistering::class);
 * ```
 *
 * ## Adding Paths After Initial Registration
 *
 * Use `addPaths()` to register additional module directories after the initial
 * registration (e.g., for dynamically loaded plugins):
 *
 * ```php
 * $registry->addPaths([base_path('plugins/custom-module')]);
 * ```
 *
 *
 * @see ModuleScanner For the discovery mechanism
 * @see LazyModuleListener For the lazy-loading wrapper
 */
class ModuleRegistry
{
    /**
     * Event-to-module mappings discovered by the scanner.
     *
     * Structure: [EventClass => [ModuleClass => ['method' => string, 'priority' => int]]]
     *
     * @var array<string, array<string, array{method: string, priority: int}>>
     */
    private array $mappings = [];

    /**
     * Whether initial registration has been performed.
     */
    private bool $registered = false;

    /**
     * Create a new ModuleRegistry instance.
     *
     * @param  ModuleScanner  $scanner  The scanner used to discover module listeners
     */
    public function __construct(
        private ModuleScanner $scanner
    ) {}

    /**
     * Scan paths and register lazy listeners for all declared events.
     *
     * Listeners are sorted by priority (highest first) before registration.
     *
     * @param  array<string>  $paths  Directories containing modules
     */
    public function register(array $paths): void
    {
        if ($this->registered) {
            return;
        }

        $this->mappings = $this->scanner->scan($paths);

        foreach ($this->mappings as $event => $listeners) {
            $sorted = $this->sortByPriority($listeners);

            foreach ($sorted as $moduleClass => $config) {
                Event::listen($event, new LazyModuleListener($moduleClass, $config['method']));
            }
        }

        $this->registered = true;
    }

    /**
     * Sort listeners by priority (highest first).
     *
     * @param  array<string, array{method: string, priority: int}>  $listeners
     * @return array<string, array{method: string, priority: int}>
     */
    private function sortByPriority(array $listeners): array
    {
        uasort($listeners, fn ($a, $b) => $b['priority'] <=> $a['priority']);

        return $listeners;
    }

    /**
     * Get all scanned mappings.
     *
     * @return array<string, array<string, array{method: string, priority: int}>> Event => [Module => config]
     */
    public function getMappings(): array
    {
        return $this->mappings;
    }

    /**
     * Get modules that listen to a specific event.
     *
     * @return array<string, array{method: string, priority: int}> Module => config
     */
    public function getListenersFor(string $event): array
    {
        return $this->mappings[$event] ?? [];
    }

    /**
     * Check if registration has been performed.
     */
    public function isRegistered(): bool
    {
        return $this->registered;
    }

    /**
     * Get all events that have listeners.
     *
     * @return array<string>
     */
    public function getEvents(): array
    {
        return array_keys($this->mappings);
    }

    /**
     * Get all modules that have declared listeners.
     *
     * @return array<string>
     */
    public function getModules(): array
    {
        $modules = [];

        foreach ($this->mappings as $listeners) {
            foreach (array_keys($listeners) as $module) {
                $modules[$module] = true;
            }
        }

        return array_keys($modules);
    }

    /**
     * Add additional paths to scan and register.
     *
     * Used by packages to register their module paths.
     * Note: Priority ordering only applies within the newly added paths.
     * For full priority control, use register() with all paths.
     *
     * @param  array<string>  $paths  Directories containing modules
     */
    public function addPaths(array $paths): void
    {
        $newMappings = $this->scanner->scan($paths);

        foreach ($newMappings as $event => $listeners) {
            $sorted = $this->sortByPriority($listeners);

            foreach ($sorted as $moduleClass => $config) {
                // Skip if already registered
                if (isset($this->mappings[$event][$moduleClass])) {
                    continue;
                }

                $this->mappings[$event][$moduleClass] = $config;
                Event::listen($event, new LazyModuleListener($moduleClass, $config['method']));
            }
        }
    }
}
