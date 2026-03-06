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
 * Base class for lifecycle events.
 *
 * Lifecycle events are fired at key points during application bootstrap. Modules
 * listen to these events via static `$listens` arrays in their Boot class and
 * register their resources through the request methods provided here.
 *
 * ## Event Flow Diagram
 *
 * The following diagram shows how lifecycle events flow through the system:
 *
 * ```
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │                        LIFECYCLE EVENT FLOW                                  │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 *     ┌──────────────────────┐
 *     │  Application Start   │
 *     └──────────┬───────────┘
 *                │
 *                ▼
 *     ┌──────────────────────┐     ┌─────────────────────────────────────────┐
 *     │  ModuleScanner       │────►│  Scans app/Core, app/Mod, app/Website   │
 *     │  (Discovery Phase)   │     │  for Boot.php files with $listens       │
 *     └──────────┬───────────┘     └─────────────────────────────────────────┘
 *                │
 *                ▼
 *     ┌──────────────────────┐     ┌─────────────────────────────────────────┐
 *     │  ModuleRegistry      │────►│  Registers LazyModuleListener for each  │
 *     │  (Registration)      │     │  event-module pair with Laravel Events  │
 *     └──────────┬───────────┘     └─────────────────────────────────────────┘
 *                │
 *                ▼
 *     ┌──────────────────────────────────────────────────────────────────────┐
 *     │                    REQUEST CONTEXT DETECTION                          │
 *     └──────────────────────────────────────────────────────────────────────┘
 *                │
 *        ┌───────┴───────┬───────────┬───────────┬───────────┬───────────┐
 *        │               │           │           │           │           │
 *        ▼               ▼           ▼           ▼           ▼           ▼
 *   ┌─────────┐    ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐
 *   │  Web    │    │  Admin  │ │  API    │ │  Client │ │ Console │ │  Queue  │
 *   │ Routes  │    │  Panel  │ │ Routes  │ │ Routes  │ │ Booting │ │ Worker  │
 *   └────┬────┘    └────┬────┘ └────┬────┘ └────┬────┘ └────┬────┘ └────┬────┘
 *        │               │           │           │           │           │
 *        ▼               ▼           ▼           ▼           ▼           ▼
 *   ┌─────────┐    ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐
 *   │WebRoutes│    │AdminPanel││ApiRoutes│ │Client   │ │Console  │ │QueueWkr │
 *   │Register-│    │Booting  │ │Register-│ │Routes   │ │Booting  │ │Booting  │
 *   │ing      │    │         │ │ing      │ │Register-│ │         │ │         │
 *   └────┬────┘    └────┬────┘ └────┬────┘ │ing      │ └────┬────┘ └────┬────┘
 *        │               │           │      └────┬────┘      │           │
 *        └───────────────┴───────────┴───────────┴───────────┴───────────┘
 *                                    │
 *                                    ▼
 *                         ┌──────────────────────┐
 *                         │  FrameworkBooted     │
 *                         │  (After all booted)  │
 *                         └──────────────────────┘
 * ```
 *
 * ## Event Order
 *
 * Events fire in a specific order based on request context:
 *
 * | Order | Event | Context | Middleware |
 * |-------|-------|---------|------------|
 * | 1 | `WebRoutesRegistering` | Web requests | 'web' |
 * | 1 | `AdminPanelBooting` | Admin requests | 'admin' |
 * | 1 | `ApiRoutesRegistering` | API requests | 'api' |
 * | 1 | `ClientRoutesRegistering` | Client dashboard | 'client' |
 * | 1 | `ConsoleBooting` | CLI commands | - |
 * | 1 | `QueueWorkerBooting` | Queue workers | - |
 * | 1 | `McpToolsRegistering` | MCP server | - |
 * | 2 | `FrameworkBooted` | All contexts | - |
 *
 * Note: Events marked "1" are mutually exclusive based on context.
 *
 * ## Module Registration Flow
 *
 * ```
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │                     MODULE REGISTRATION FLOW                                 │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 *     Module Boot.php                    Framework
 *     ─────────────────                  ─────────
 *           │
 *           │  public static array $listens = [
 *           │      WebRoutesRegistering::class => 'onWebRoutes',
 *           │      AdminPanelBooting::class => ['onAdmin', 10],
 *           │  ];
 *           │
 *           │                                   │
 *           │◄──────────────────────────────────┤ ModuleScanner reads $listens
 *           │                                   │ without instantiation
 *           │                                   │
 *           │                                   ▼
 *           │                            ┌─────────────────────┐
 *           │                            │ ModuleRegistry      │
 *           │                            │ sorts by priority   │
 *           │                            │ (10 runs before 0)  │
 *           │                            └──────────┬──────────┘
 *           │                                       │
 *           │                                       ▼
 *           │                            ┌─────────────────────┐
 *           │                            │ LazyModuleListener  │
 *           │                            │ registered with     │
 *           │                            │ Laravel Events      │
 *           │                            └──────────┬──────────┘
 *           │                                       │
 *           │                                       │ Event fires
 *           │                                       ▼
 *           │                            ┌─────────────────────┐
 *           │                            │ LazyModuleListener  │
 *           │◄───────────────────────────┤ instantiates module │
 *           │  Module instantiated       │ via container       │
 *           │  only when event fires     └──────────┬──────────┘
 *           │                                       │
 *           ▼                                       │
 *     ┌─────────────┐                               │
 *     │ onWebRoutes │◄──────────────────────────────┘
 *     │ ($event)    │  Method called with event
 *     └──────┬──────┘
 *            │
 *            ▼
 *     ┌──────────────────────────────────────────────────────┐
 *     │ $event->routes(fn () => require __DIR__.'/web.php'); │
 *     │ $event->views('mymod', __DIR__.'/Views');            │
 *     │ $event->livewire('my-comp', MyComponent::class);     │
 *     └──────────────────────────────────────────────────────┘
 *            │
 *            │ Requests collected in event
 *            ▼
 *     ┌─────────────────────────────────────────────────────────────┐
 *     │ LifecycleEventProvider processes requests:                  │
 *     │ - Registers view namespaces                                 │
 *     │ - Registers Livewire components                             │
 *     │ - Wraps routes with appropriate middleware                  │
 *     │ - Refreshes route lookups                                   │
 *     └─────────────────────────────────────────────────────────────┘
 * ```
 *
 * ## Request/Collect Pattern
 *
 * This class implements a "request/collect" pattern rather than direct mutation:
 *
 * 1. **Modules request** resources via methods like `routes()`, `views()`, etc.
 * 2. **Requests are collected** in arrays during event dispatch
 * 3. **LifecycleEventProvider processes** collected requests with validation
 *
 * This pattern ensures modules cannot directly mutate infrastructure and allows
 * the framework to validate, sort, and process requests centrally.
 *
 * ## Available Request Methods
 *
 * | Method | Purpose |
 * |--------|---------|
 * | `routes()` | Register route files/callbacks |
 * | `views()` | Register view namespaces |
 * | `livewire()` | Register Livewire components |
 * | `middleware()` | Register middleware aliases |
 * | `command()` | Register Artisan commands |
 * | `translations()` | Register translation namespaces |
 * | `bladeComponentPath()` | Register anonymous Blade component paths |
 * | `policy()` | Register model policies |
 * | `navigation()` | Register navigation items |
 *
 * ## Event Versioning
 *
 * Events support versioning for backwards compatibility. The version number
 * indicates the API contract version:
 *
 * - Version 1: Original API (current)
 * - Future versions may add methods but maintain backwards compatibility
 *
 * Check version with `$event->version()` in your handlers to support multiple
 * event versions during transitions.
 *
 * ## Usage Example
 *
 * ```php
 * public function onWebRoutes(WebRoutesRegistering $event): void
 * {
 *     $event->views('mymodule', __DIR__.'/Views');
 *     $event->livewire('my-component', MyComponent::class);
 *     $event->routes(fn () => require __DIR__.'/Routes/web.php');
 * }
 * ```
 *
 *
 * @method void navigation(array $item) Request a navigation item be added
 * @method void routes(callable $callback) Request routes be registered
 * @method void views(string $namespace, string $path) Request a view namespace be registered
 * @method void middleware(string $alias, string $class) Request a middleware alias be registered
 * @method void livewire(string $alias, string $class) Request a Livewire component be registered
 * @method void command(string $class) Request an Artisan command be registered
 * @method void translations(string $namespace, string $path) Request translations be loaded
 * @method void bladeComponentPath(string $path, ?string $namespace = null) Request a Blade component path
 * @method void policy(string $model, string $policy) Request a policy be registered
 * @method array navigationRequests() Get collected navigation requests
 * @method array routeRequests() Get collected route requests
 * @method array viewRequests() Get collected view requests
 * @method array middlewareRequests() Get collected middleware requests
 * @method array livewireRequests() Get collected Livewire requests
 * @method array commandRequests() Get collected command requests
 * @method array translationRequests() Get collected translation requests
 * @method array bladeComponentRequests() Get collected Blade component requests
 * @method array policyRequests() Get collected policy requests
 *
 * @see LifecycleEventProvider For event processing
 */
abstract class LifecycleEvent
{
    /**
     * Event API version.
     *
     * Increment this when making breaking changes to the event interface.
     * Handlers can check this to maintain backwards compatibility.
     *
     * Version history:
     * - 1: Initial release (Core PHP 1.0)
     */
    public const VERSION = 1;

    /**
     * Minimum supported handler version.
     *
     * Handlers declaring a version lower than this will receive a deprecation warning.
     */
    public const MIN_SUPPORTED_VERSION = 1;

    /** @var array<int, array<string, mixed>> Collected navigation item requests */
    protected array $navigationRequests = [];

    /** @var array<int, callable> Collected route registration callbacks */
    protected array $routeRequests = [];

    /** @var array<int, array{0: string, 1: string}> Collected view namespace requests [namespace, path] */
    protected array $viewRequests = [];

    /** @var array<int, array{0: string, 1: string}> Collected middleware alias requests [alias, class] */
    protected array $middlewareRequests = [];

    /** @var array<int, array{0: string, 1: string}> Collected Livewire component requests [alias, class] */
    protected array $livewireRequests = [];

    /** @var array<int, string> Collected Artisan command class names */
    protected array $commandRequests = [];

    /** @var array<int, array{0: string, 1: string}> Collected translation namespace requests [namespace, path] */
    protected array $translationRequests = [];

    /** @var array<int, array{0: string, 1: string|null}> Collected Blade component path requests [path, namespace] */
    protected array $bladeComponentRequests = [];

    /** @var array<int, array{0: string, 1: string}> Collected policy requests [model, policy] */
    protected array $policyRequests = [];

    /**
     * Request a navigation item be added.
     *
     * Navigation items are collected and processed by the admin menu system.
     * Consider implementing AdminMenuProvider for more control over menu items.
     *
     * @param  array<string, mixed>  $item  Navigation item configuration
     */
    public function navigation(array $item): void
    {
        $this->navigationRequests[] = $item;
    }

    /**
     * Request routes be registered.
     *
     * The callback is invoked within the appropriate middleware group
     * (web, admin, api, client) depending on which event fired.
     *
     * ```php
     * $event->routes(fn () => require __DIR__.'/Routes/web.php');
     * // or
     * $event->routes(function () {
     *     Route::get('/example', ExampleController::class);
     * });
     * ```
     *
     * @param  callable  $callback  Route registration callback
     */
    public function routes(callable $callback): void
    {
        $this->routeRequests[] = $callback;
    }

    /**
     * Request a view namespace be registered.
     *
     * After registration, views can be referenced as `namespace::view.name`.
     *
     * ```php
     * $event->views('commerce', __DIR__.'/Views');
     * // Later: view('commerce::products.index')
     * ```
     *
     * @param  string  $namespace  The view namespace (e.g., 'commerce')
     * @param  string  $path  Absolute path to the views directory
     */
    public function views(string $namespace, string $path): void
    {
        $this->viewRequests[] = [$namespace, $path];
    }

    /**
     * Request a middleware alias be registered.
     *
     * @param  string  $alias  The middleware alias (e.g., 'commerce.auth')
     * @param  string  $class  Fully qualified middleware class name
     */
    public function middleware(string $alias, string $class): void
    {
        $this->middlewareRequests[] = [$alias, $class];
    }

    /**
     * Request a Livewire component be registered.
     *
     * ```php
     * $event->livewire('commerce-cart', CartComponent::class);
     * // Later: <livewire:commerce-cart />
     * ```
     *
     * @param  string  $alias  The component alias used in Blade templates
     * @param  string  $class  Fully qualified Livewire component class name
     */
    public function livewire(string $alias, string $class): void
    {
        $this->livewireRequests[] = [$alias, $class];
    }

    /**
     * Request an Artisan command be registered.
     *
     * Only processed during ConsoleBooting event.
     *
     * @param  string  $class  Fully qualified command class name
     */
    public function command(string $class): void
    {
        $this->commandRequests[] = $class;
    }

    /**
     * Request translations be loaded for a namespace.
     *
     * After registration, translations can be accessed as `namespace::key`.
     *
     * ```php
     * $event->translations('commerce', __DIR__.'/Lang');
     * // Later: __('commerce::products.title')
     * ```
     *
     * @param  string  $namespace  The translation namespace
     * @param  string  $path  Absolute path to the lang directory
     */
    public function translations(string $namespace, string $path): void
    {
        $this->translationRequests[] = [$namespace, $path];
    }

    /**
     * Request an anonymous Blade component path be registered.
     *
     * Anonymous components in this path can be used in templates.
     *
     * @param  string  $path  Absolute path to the components directory
     * @param  string|null  $namespace  Optional prefix for component names
     */
    public function bladeComponentPath(string $path, ?string $namespace = null): void
    {
        $this->bladeComponentRequests[] = [$path, $namespace];
    }

    /**
     * Request a policy be registered for a model.
     *
     * @param  string  $model  Fully qualified model class name
     * @param  string  $policy  Fully qualified policy class name
     */
    public function policy(string $model, string $policy): void
    {
        $this->policyRequests[] = [$model, $policy];
    }

    /**
     * Get all navigation requests for processing.
     *
     * @return array<int, array<string, mixed>>
     *
     * @internal Used by LifecycleEventProvider
     */
    public function navigationRequests(): array
    {
        return $this->navigationRequests;
    }

    /**
     * Get all route requests for processing.
     *
     * @return array<int, callable>
     *
     * @internal Used by LifecycleEventProvider
     */
    public function routeRequests(): array
    {
        return $this->routeRequests;
    }

    /**
     * Get all view namespace requests for processing.
     *
     * @return array<int, array{0: string, 1: string}>
     *
     * @internal Used by LifecycleEventProvider
     */
    public function viewRequests(): array
    {
        return $this->viewRequests;
    }

    /**
     * Get all middleware alias requests for processing.
     *
     * @return array<int, array{0: string, 1: string}>
     *
     * @internal Used by LifecycleEventProvider
     */
    public function middlewareRequests(): array
    {
        return $this->middlewareRequests;
    }

    /**
     * Get all Livewire component requests for processing.
     *
     * @return array<int, array{0: string, 1: string}>
     *
     * @internal Used by LifecycleEventProvider
     */
    public function livewireRequests(): array
    {
        return $this->livewireRequests;
    }

    /**
     * Get all command requests for processing.
     *
     * @return array<int, string>
     *
     * @internal Used by LifecycleEventProvider
     */
    public function commandRequests(): array
    {
        return $this->commandRequests;
    }

    /**
     * Get all translation requests for processing.
     *
     * @return array<int, array{0: string, 1: string}>
     *
     * @internal Used by LifecycleEventProvider
     */
    public function translationRequests(): array
    {
        return $this->translationRequests;
    }

    /**
     * Get all Blade component path requests for processing.
     *
     * @return array<int, array{0: string, 1: string|null}>
     *
     * @internal Used by LifecycleEventProvider
     */
    public function bladeComponentRequests(): array
    {
        return $this->bladeComponentRequests;
    }

    /**
     * Get all policy requests for processing.
     *
     * @return array<int, array{0: string, 1: string}>
     *
     * @internal Used by LifecycleEventProvider
     */
    public function policyRequests(): array
    {
        return $this->policyRequests;
    }

    /**
     * Get the event API version.
     *
     * Use this in your event handlers to check API compatibility:
     *
     * ```php
     * public function onWebRoutes(WebRoutesRegistering $event): void
     * {
     *     if ($event->version() >= 2) {
     *         // Use new v2 features
     *     } else {
     *         // Fallback to v1 behavior
     *     }
     * }
     * ```
     *
     * @return int The event API version number
     */
    public function version(): int
    {
        return static::VERSION;
    }

    /**
     * Check if this event supports a specific version.
     *
     * Returns true if the event's version is greater than or equal to the
     * requested version.
     *
     * @param  int  $version  The version to check against
     * @return bool True if the event supports the specified version
     */
    public function supportsVersion(int $version): bool
    {
        return static::VERSION >= $version;
    }

    /**
     * Get the event class name without namespace.
     *
     * Useful for logging and debugging.
     *
     * @return string The short class name (e.g., 'WebRoutesRegistering')
     */
    public function eventName(): string
    {
        return class_basename(static::class);
    }
}
