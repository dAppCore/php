# Events

Lifecycle events that drive the module loading system.

## What It Does

Defines the event classes that modules listen to via `static $listens` arrays in their Boot classes. Events use a request/collect pattern: modules call methods like `routes()`, `views()`, `livewire()` during event dispatch, and `LifecycleEventProvider` processes the collected requests afterwards.

This is the **core of the module loading architecture**. Modules are never instantiated until their listened events fire.

## Lifecycle Events (Mutually Exclusive by Context)

| Event | Context | Middleware | Purpose |
|-------|---------|------------|---------|
| `WebRoutesRegistering` | Web requests | `web` | Public-facing routes, views |
| `AdminPanelBooting` | Admin requests | `admin` | Admin dashboard resources |
| `ApiRoutesRegistering` | API requests | `api` | REST API endpoints |
| `ClientRoutesRegistering` | Client dashboard | `client` | Authenticated SaaS user routes |
| `ConsoleBooting` | CLI | -- | Artisan commands |
| `QueueWorkerBooting` | Queue workers | -- | Job registration, queue init |
| `McpToolsRegistering` | MCP server | -- | MCP tool handlers |
| `McpRoutesRegistering` | MCP HTTP | `mcp` | MCP HTTP endpoints |
| `FrameworkBooted` | All contexts | -- | Late-stage cross-cutting init |

## Capability Events (On-Demand)

| Event | Purpose |
|-------|---------|
| `DomainResolving` | Multi-tenancy by domain. First provider to `register()` wins |
| `SearchRequested` | Lazy-load search: `searchable(Model::class)` |
| `MediaRequested` | Lazy-load media: `processor('image', ImageProcessor::class)` |
| `MailSending` | Lazy-load mail: `mailable(WelcomeEmail::class)` |

## Base Class: LifecycleEvent

All lifecycle events extend `LifecycleEvent`, which provides these request methods:

| Method | Purpose |
|--------|---------|
| `routes(callable)` | Register route callback |
| `views(namespace, path)` | Register view namespace |
| `livewire(alias, class)` | Register Livewire component |
| `middleware(alias, class)` | Register middleware alias |
| `command(class)` | Register Artisan command |
| `translations(namespace, path)` | Register translation namespace |
| `bladeComponentPath(path, namespace)` | Register anonymous Blade components |
| `policy(model, policy)` | Register model policy |
| `navigation(item)` | Register nav item |

Each has a corresponding `*Requests()` getter for `LifecycleEventProvider` to process.

## Observability

| Class | Purpose |
|-------|---------|
| `ListenerProfiler` | Measures execution time, memory, call count per listener. `enable()`, `getSlowListeners()`, `getSlowest(10)`, `getSummary()`, `export()` |
| `EventAuditLog` | Tracks success/failure of event handlers. `enable()`, `entries()`, `failures()`, `summary()` |

## Event Versioning

| Class | Purpose |
|-------|---------|
| `HasEventVersion` (trait) | Modules declare `$eventVersions` for compatibility checking |

Events carry `VERSION` and `MIN_SUPPORTED_VERSION` constants. Handlers check `$event->version()` or `$event->supportsVersion(2)` for forward compatibility.

## Integration

```php
// Module Boot class
class Boot {
    public static array $listens = [
        WebRoutesRegistering::class => 'onWebRoutes',
        AdminPanelBooting::class => ['onAdmin', 10], // with priority
    ];

    public function onWebRoutes(WebRoutesRegistering $event): void {
        $event->views('mymod', __DIR__.'/Views');
        $event->routes(fn() => require __DIR__.'/web.php');
    }
}
```

Flow: `ModuleScanner` reads `$listens` -> `ModuleRegistry` registers `LazyModuleListener` with Laravel Events -> Event fires -> Module instantiated via container -> Method called with event -> Requests collected -> `LifecycleEventProvider` processes.
