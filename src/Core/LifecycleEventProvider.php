<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core;

use Core\Events\AdminPanelBooting;
use Core\Events\ApiRoutesRegistering;
use Core\Events\ClientRoutesRegistering;
use Core\Events\ConsoleBooting;
use Core\Events\FrameworkBooted;
use Core\Events\McpRoutesRegistering;
use Core\Events\McpToolsRegistering;
use Core\Events\QueueWorkerBooting;
use Core\Events\WebRoutesRegistering;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/**
 * Orchestrates lifecycle events for lazy module loading.
 *
 * The LifecycleEventProvider is the entry point for the event-driven module system.
 * It coordinates module discovery, listener registration, and event firing at
 * appropriate points during the application lifecycle.
 *
 * ## Lifecycle Event Firing Sequence
 *
 * ```
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │                   LIFECYCLE EVENT FIRING SEQUENCE                            │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 *     Application
 *         │
 *         ├─── register() ──────────────────────────────────────────────────────┐
 *         │         │                                                            │
 *         │         ├── ModuleScanner::scan()                                    │
 *         │         │       Discovers Boot.php files with $listens               │
 *         │         │                                                            │
 *         │         └── ModuleRegistry::register()                               │
 *         │                 Wires LazyModuleListener for each event/module       │
 *         │                                                                      │
 *         ├─── boot() ──────────────────────────────────────────────────────────┤
 *         │         │                                                            │
 *         │         ├── (if queue.worker bound)                                  │
 *         │         │       └── fireQueueWorkerBooting()                         │
 *         │         │               Fires: QueueWorkerBooting                    │
 *         │         │                                                            │
 *         │         └── $app->booted() callback registered                       │
 *         │                 └── Fires: FrameworkBooted                           │
 *         │                                                                      │
 *         │                                                                      │
 *     ┌───┴─────────────────────────────────────────────────────────────────────┤
 *     │   FRONTAGE MODULES FIRE CONTEXT-SPECIFIC EVENTS                          │
 *     └──────────────────────────────────────────────────────────────────────────┤
 *         │                                                                      │
 *         ├─── Front/Web/Boot ────────────────────────────────────────────────── │
 *         │         └── LifecycleEventProvider::fireWebRoutes()                  │
 *         │                 Fires: WebRoutesRegistering                          │
 *         │                 Processes: views, livewire, routes ('web' middleware)│
 *         │                                                                      │
 *         ├─── Front/Admin/Boot ──────────────────────────────────────────────── │
 *         │         └── LifecycleEventProvider::fireAdminBooting()               │
 *         │                 Fires: AdminPanelBooting                             │
 *         │                 Processes: views, translations, livewire, routes     │
 *         │                            ('admin' middleware)                      │
 *         │                                                                      │
 *         ├─── Front/Api/Boot (php-api package) ─────────────────────────────── │
 *         │         └── LifecycleEventProvider::fireApiRoutes()                  │
 *         │                 Fires: ApiRoutesRegistering                          │
 *         │                 Processes: routes ('api' middleware)                │
 *         │                                                                      │
 *         ├─── Front/Client/Boot ─────────────────────────────────────────────── │
 *         │         └── LifecycleEventProvider::fireClientRoutes()               │
 *         │                 Fires: ClientRoutesRegistering                       │
 *         │                 Processes: views, livewire, routes ('client' mw)     │
 *         │                                                                      │
 *         ├─── Front/Cli/Boot ────────────────────────────────────────────────── │
 *         │         └── LifecycleEventProvider::fireConsoleBooting()             │
 *         │                 Fires: ConsoleBooting                                │
 *         │                 Processes: command classes                           │
 *         │                                                                      │
 *         └─── Front/Mcp/Boot (php-mcp package) ─────────────────────────────── │
 *                   ├── LifecycleEventProvider::fireMcpRoutes()                  │
 *                   │       Fires: McpRoutesRegistering                          │
 *                   │       Processes: routes ('mcp' middleware)                 │
 *                   │                                                            │
 *                   └── LifecycleEventProvider::fireMcpTools()                   │
 *                           Fires: McpToolsRegistering                           │
 *                           Returns: MCP tool handler classes                    │
 *                                                                                │
 * └──────────────────────────────────────────────────────────────────────────────┘
 * ```
 *
 * ## Lifecycle Phases
 *
 * **Registration Phase (register())**
 * - Registers ModuleScanner and ModuleRegistry as singletons
 * - Scans configured paths for Boot classes with `$listens` declarations
 * - Wires lazy listeners for each event-module pair
 *
 * **Boot Phase (boot())**
 * - Fires queue worker event if in queue context
 * - Schedules FrameworkBooted event via `$app->booted()`
 *
 * **Event Firing (static fire* methods)**
 * - Called by frontage modules (Web, Admin, Api, etc.) at appropriate times
 * - Fire events, collect requests, and process them with appropriate middleware
 *
 * ## Request Processing Flow
 *
 * ```
 * Event created ──► event() dispatched ──► Listeners collect requests
 *                                                    │
 *                                                    ▼
 *                                          ┌─────────────────────┐
 *                                          │ $event->routes()    │
 *                                          │ $event->views()     │
 *                                          │ $event->livewire()  │
 *                                          └─────────┬───────────┘
 *                                                    │
 *                                                    ▼
 *                                          ┌─────────────────────┐
 *                                          │ fire*() processes   │
 *                                          │ collected requests: │
 *                                          │ - View namespaces   │
 *                                          │ - Livewire comps    │
 *                                          │ - Middleware routes │
 *                                          └─────────────────────┘
 * ```
 *
 * ## Module Declaration
 *
 * Modules declare interest in events via static `$listens` arrays in their Boot class:
 *
 * ```php
 * class Boot
 * {
 *     public static array $listens = [
 *         WebRoutesRegistering::class => 'onWebRoutes',
 *         AdminPanelBooting::class => 'onAdmin',
 *         ConsoleBooting::class => ['onConsole', 10],  // With priority
 *     ];
 *
 *     public function onWebRoutes(WebRoutesRegistering $event): void
 *     {
 *         $event->routes(fn () => require __DIR__.'/Routes/web.php');
 *         $event->views('mymodule', __DIR__.'/Views');
 *     }
 * }
 * ```
 *
 * The module is only instantiated when its registered events actually fire,
 * enabling efficient lazy loading based on request context.
 *
 * ## Default Scan Paths
 *
 * By default, scans these directories under `app_path()`:
 * - `Core` - Core system modules
 * - `Mod` - Feature modules
 * - `Website` - Website/domain-specific modules
 *
 *
 * @see ModuleScanner For module discovery
 * @see ModuleRegistry For listener registration
 * @see LazyModuleListener For lazy instantiation
 */
class LifecycleEventProvider extends ServiceProvider
{
    /**
     * Directories to scan for modules with $listens declarations.
     *
     * @var array<string>
     */
    protected array $scanPaths = [];

    /**
     * Register module infrastructure and wire lazy listeners.
     *
     * This method:
     * 1. Registers ModuleScanner and ModuleRegistry as singletons
     * 2. Configures default scan paths (Core, Mod, Website)
     * 3. Triggers module scanning and listener registration
     *
     * Runs early in the application lifecycle before boot().
     */
    public function register(): void
    {
        // Register infrastructure
        $this->app->singleton(ModuleScanner::class);
        $this->app->singleton(ModuleRegistry::class, function ($app) {
            return new ModuleRegistry($app->make(ModuleScanner::class));
        });

        // Scan and wire lazy listeners
        // Start with configured application module paths
        $this->scanPaths = config('core.module_paths', [
            app_path('Core'),
            app_path('Mod'),
            app_path('Website'),
        ]);

        // Add framework's own module paths (works in vendor/ or packages/)
        $frameworkSrcPath = dirname(__DIR__);  // .../src/Core -> .../src
        $this->scanPaths[] = $frameworkSrcPath.'/Core';  // Core\*\Boot
        $this->scanPaths[] = $frameworkSrcPath.'/Mod';   // Mod\*\Boot

        // Filter to only existing directories
        $this->scanPaths = array_filter($this->scanPaths, 'is_dir');

        $registry = $this->app->make(ModuleRegistry::class);
        $registry->register($this->scanPaths);
    }

    /**
     * Boot the provider and schedule late-stage events.
     *
     * Fires queue worker event if running in queue context, and schedules
     * the FrameworkBooted event to fire after all providers have booted.
     *
     * Note: Most lifecycle events (Web, Admin, API, etc.) are fired by their
     * respective frontage modules, not here.
     */
    public function boot(): void
    {
        // Console event now fired by Core\Front\Cli\Boot

        // Fire queue worker event for queue context
        if ($this->app->bound('queue.worker')) {
            $this->fireQueueWorkerBooting();
        }

        // Framework booted event fires after all providers have booted
        $this->app->booted(function () {
            event(new FrameworkBooted);
        });
    }

    /**
     * Register middleware aliases collected by a lifecycle event.
     *
     * Every fire* method calls this so modules can register middleware
     * aliases via `$event->middleware('alias', Class::class)` on any event.
     */
    protected static function processMiddleware(Events\LifecycleEvent $event): void
    {
        /** @var \Illuminate\Routing\Router $router */
        $router = app('router');

        foreach ($event->middlewareRequests() as [$alias, $class]) {
            $router->aliasMiddleware($alias, $class);
        }
    }

    /**
     * Fire WebRoutesRegistering and process collected requests.
     *
     * Called by Front/Web/Boot when web middleware is being set up. This method:
     *
     * 1. Fires the WebRoutesRegistering event to all listeners
     * 2. Processes view namespace requests (adds them to the view finder)
     * 3. Processes Livewire component requests (registers with Livewire)
     * 4. Processes route requests (wraps with 'web' middleware)
     * 5. Refreshes route name and action lookups
     *
     * Routes registered through this event are automatically wrapped with
     * the 'web' middleware group for session, CSRF, etc.
     */
    public static function fireWebRoutes(): void
    {
        $event = new WebRoutesRegistering;
        event($event);

        static::processMiddleware($event);

        // Process view namespace requests
        foreach ($event->viewRequests() as [$namespace, $path]) {
            if (is_dir($path)) {
                view()->addNamespace($namespace, $path);
            }
        }

        // Process Livewire component requests
        foreach ($event->livewireRequests() as [$alias, $class]) {
            if (class_exists(Livewire::class)) {
                Livewire::component($alias, $class);
            }
        }

        // Process route requests
        foreach ($event->routeRequests() as $callback) {
            Route::middleware('web')->group($callback);
        }

        // Refresh route lookups after adding routes
        app('router')->getRoutes()->refreshNameLookups();
        app('router')->getRoutes()->refreshActionLookups();
    }

    /**
     * Fire AdminPanelBooting and process collected requests.
     *
     * Called by Front/Admin/Boot when admin routes are being set up. This method:
     *
     * 1. Fires the AdminPanelBooting event to all listeners
     * 2. Processes view namespace requests
     * 3. Processes translation namespace requests
     * 4. Processes Livewire component requests
     * 5. Processes route requests (wraps with 'admin' middleware)
     *
     * Routes registered through this event are automatically wrapped with
     * the 'admin' middleware group for authentication, authorization, etc.
     *
     * Navigation items are handled separately via AdminMenuProvider interface.
     */
    public static function fireAdminBooting(): void
    {
        $event = new AdminPanelBooting;
        event($event);

        static::processMiddleware($event);

        // Process view namespace requests
        foreach ($event->viewRequests() as [$namespace, $path]) {
            if (is_dir($path)) {
                view()->addNamespace($namespace, $path);
            }
        }

        // Process translation requests
        foreach ($event->translationRequests() as [$namespace, $path]) {
            if (is_dir($path)) {
                app('translator')->addNamespace($namespace, $path);
            }
        }

        // Process Livewire component requests
        foreach ($event->livewireRequests() as [$alias, $class]) {
            if (class_exists(Livewire::class)) {
                Livewire::component($alias, $class);
            }
        }

        // Process route requests with admin middleware
        foreach ($event->routeRequests() as $callback) {
            Route::middleware('admin')->group($callback);
        }

        // Note: Navigation is handled via AdminMenuProvider interface.
        // Modules implementing that interface will have their navigation
        // registered through the existing AdminMenuRegistry::register() call.
        // The $event->navigation() requests are available for future use
        // when we move away from the AdminMenuProvider pattern.
    }

    /**
     * Fire ClientRoutesRegistering and process collected requests.
     *
     * Called by Front/Client/Boot when client dashboard routes are being set up.
     * This is for authenticated SaaS customers managing their namespace (bio pages,
     * settings, analytics, etc.).
     *
     * Routes registered through this event are automatically wrapped with
     * the 'client' middleware group.
     */
    public static function fireClientRoutes(): void
    {
        $event = new ClientRoutesRegistering;
        event($event);

        static::processMiddleware($event);

        // Process view namespace requests
        foreach ($event->viewRequests() as [$namespace, $path]) {
            if (is_dir($path)) {
                view()->addNamespace($namespace, $path);
            }
        }

        // Process Livewire component requests
        foreach ($event->livewireRequests() as [$alias, $class]) {
            if (class_exists(Livewire::class)) {
                Livewire::component($alias, $class);
            }
        }

        // Process route requests with client middleware
        foreach ($event->routeRequests() as $callback) {
            Route::middleware('client')->group($callback);
        }

        // Refresh route lookups after adding routes
        app('router')->getRoutes()->refreshNameLookups();
        app('router')->getRoutes()->refreshActionLookups();
    }

    /**
     * Fire ApiRoutesRegistering and process collected requests.
     *
     * Called by Front/Api/Boot when REST API routes are being set up.
     *
     * Routes registered through this event are automatically wrapped
     * with the 'api' middleware group (stateless, no CSRF).
     * No prefix is applied — API routes live on domain-scoped subdomains
     * (e.g., api.lthn.ai/v1/brain/recall).
     */
    public static function fireApiRoutes(): void
    {
        $event = new ApiRoutesRegistering;
        event($event);

        static::processMiddleware($event);

        // Process route requests with api middleware
        foreach ($event->routeRequests() as $callback) {
            Route::middleware('api')->group($callback);
        }
    }

    /**
     * Fire McpRoutesRegistering and process collected requests.
     *
     * Called by Front/Mcp/Boot when MCP protocol routes are being set up.
     * Routes registered through this event are automatically wrapped with
     * the 'mcp' middleware group (stateless, rate limiting).
     *
     * No prefix is applied — MCP routes live at the domain root
     * (e.g., mcp.host.uk.com/tools/call).
     */
    public static function fireMcpRoutes(): void
    {
        $event = new McpRoutesRegistering;
        event($event);

        static::processMiddleware($event);

        // Process route requests with mcp middleware
        foreach ($event->routeRequests() as $callback) {
            Route::middleware('mcp')->group($callback);
        }
    }

    /**
     * Fire McpToolsRegistering and return collected handler classes.
     *
     * Called by the MCP (Model Context Protocol) server command when loading tools.
     * Modules register their MCP tool handlers through this event.
     *
     * @return array<string> Fully qualified class names of McpToolHandler implementations
     *
     * @see \Core\Front\Mcp\Contracts\McpToolHandler (in php-mcp package)
     */
    public static function fireMcpTools(): array
    {
        $event = new McpToolsRegistering;
        event($event);

        return $event->handlers();
    }

    /**
     * Fire ConsoleBooting and register collected Artisan commands.
     *
     * Called when running in CLI context. Modules register their Artisan
     * commands through the event's `command()` method.
     */
    protected function fireConsoleBooting(): void
    {
        $event = new ConsoleBooting;
        event($event);

        static::processMiddleware($event);

        // Process command requests
        if (! empty($event->commandRequests())) {
            $this->commands($event->commandRequests());
        }
    }

    /**
     * Fire QueueWorkerBooting for queue worker context.
     *
     * Called when the application is running as a queue worker. Modules can
     * use this event for queue-specific initialization.
     */
    protected function fireQueueWorkerBooting(): void
    {
        $event = new QueueWorkerBooting;
        event($event);

        // Job registration handled by Laravel's queue system
    }
}
