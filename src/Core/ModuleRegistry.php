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
        private readonly ModuleScanner $scanner
    ) {
    }

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

        // Merged into what is already here, not assigned over it. A package that
        // calls registerClass() from its own provider may well do so before this
        // runs — provider order is Laravel's to decide — and assigning threw that
        // record away, so the guard below could not see it. The class was then
        // wired a second time and its handler ran twice on every event.
        foreach ($this->scanner->scan($paths) as $event => $listeners) {
            foreach ($this->sortByPriority($listeners) as $moduleClass => $config) {
                if (isset($this->mappings[$event][$moduleClass])) {
                    continue;
                }

                $this->mappings[$event][$moduleClass] = $config;
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
        uasort($listeners, fn ($a, $b): int => $b['priority'] <=> $a['priority']);

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

    /**
     * Register one Boot class's `$listens` by name, without scanning for it.
     *
     * This is how a package outside the scanned tree takes part in lifecycle
     * events — which in practice means every package in vendor/.
     *
     *     // in the package's Boot::register()
     *     $this->app->make(ModuleRegistry::class)->registerClass(static::class);
     *
     * ## Why by name rather than by path
     *
     * Scanning has to work out a class name from a directory, and a package may
     * lay itself out however it likes: php-uptelligence puts `Core\Mod\Uptelligence`
     * at its package root, php-commerce keeps `Core\Service\Commerce` under
     * `Service/`, php-admin has `Core\Mod\Hub` under `src/Mod/Hub`. No directory
     * convention describes all of those, and one that guesses wrong does not
     * fail loudly — it produces a name that does not exist, `class_exists()`
     * returns false, and the module is skipped in silence.
     *
     * `static::class` is not a guess. The Boot class already knows what it is
     * called, and every one of these packages is already a ServiceProvider that
     * Laravel has constructed, so there is a moment where the name is simply
     * available. That is the moment to use it.
     *
     * ## The trap this exists to close
     *
     * A `$listens` array on a class nothing scans is dead code that reads as
     * live. It declares handlers, they are never called, and nothing anywhere
     * reports a problem — the feature is just quietly absent. If you are writing
     * a package with a `$listens` array, call this; declaring the array is not
     * enough on its own.
     *
     * ## Ordering
     *
     * Priorities order listeners registered together in one pass. A module that
     * registers itself is appended when its provider runs, so its priority
     * orders it against others registered in the same call and not against the
     * scanned tree. If two modules must run in a fixed order relative to each
     * other, that is a reason for them to be scanned together rather than to
     * lean on this.
     *
     * Registering the same class twice is a no-op, so a package that both is
     * scanned and calls this does not get its handlers run twice.
     *
     * @param  class-string  $class  The Boot class to register
     */
    public function registerClass(string $class): void
    {
        foreach ($this->scanner->extractListens($class) as $event => $config) {
            if (isset($this->mappings[$event][$class])) {
                continue;
            }

            $this->mappings[$event][$class] = $config;
            Event::listen($event, new LazyModuleListener($class, $config['method']));
        }
    }
}
