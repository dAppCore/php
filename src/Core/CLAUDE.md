# Core Orchestration

Root-level files in `src/Core/` that wire the entire framework together. These are the bootstrap, module discovery, lazy loading, and pro-feature detection systems.

## Files

| File | Purpose |
|------|---------|
| `Init.php` | True entry point. `Core\Init::handle()` replaces Laravel's `bootstrap/app.php`. Runs WAF input filtering via `Input::capture()`, then delegates to `Boot::app()`. Prefers `App\Boot` if it exists. |
| `Boot.php` | Configures Laravel `Application` with providers, middleware, and exceptions. Provider load order is critical: `LifecycleEventProvider` -> `Website\Boot` -> `Front\Boot` -> `Mod\Boot`. |
| `LifecycleEventProvider.php` | The orchestrator. Registers `ModuleScanner` and `ModuleRegistry` as singletons, scans configured paths, wires lazy listeners. Static `fire*()` methods are called by frontage modules to dispatch lifecycle events and process collected requests (views, livewire, routes, middleware). |
| `ModuleScanner.php` | Discovers `Boot.php` files in subdirectories of given paths. Reads static `$listens` arrays via reflection without instantiating modules. Maps paths to namespaces (`/Core` -> `Core\`, `/Mod` -> `Mod\`, `/Website` -> `Website\`, `/Plug` -> `Plug\`). |
| `ModuleRegistry.php` | Coordinates scanner output into Laravel's event system. Sorts listeners by priority (highest first), creates `LazyModuleListener` instances, supports late-registration via `addPaths()`. |
| `LazyModuleListener.php` | The lazy-loading wrapper. Instantiates module on first event fire (cached thereafter). ServiceProviders use `resolveProvider()`, plain classes use `make()`. Records audit logs and profiling data. |
| `Pro.php` | Detects Flux Pro and FontAwesome Pro installations. Auto-enables pro features, falls back gracefully to free equivalents. Throws helpful dev-mode exceptions. |
| `config.php` | Framework configuration: branding, domains, CDN, organisation, social links, contact, FontAwesome, pro fallback behaviour, icon defaults, debug settings, seeder auto-discovery. |

## Bootstrap Sequence

```
public/index.php
  -> Core\Init::handle()
       -> Input::capture()           # WAF layer sanitises $_GET/$_POST
       -> Boot::app()                # Build Laravel Application
            -> LifecycleEventProvider # register(): scan + wire lazy listeners
            -> Website\Boot          # register(): domain resolution
            -> Front\Boot            # boot(): fires lifecycle events
            -> Mod\Boot              # aggregates feature modules
```

## Module Declaration Pattern

Modules declare interest in events via static `$listens`:

```php
class Boot
{
    public static array $listens = [
        WebRoutesRegistering::class => 'onWebRoutes',
        AdminPanelBooting::class => ['onAdmin', 10],  // priority 10
    ];
}
```

Modules are never instantiated until their event fires.

## Lifecycle Events (fire* methods)

| Method | Event | Middleware | Processes |
|--------|-------|-----------|-----------|
| `fireWebRoutes()` | `WebRoutesRegistering` | `web` | views, livewire, routes |
| `fireAdminBooting()` | `AdminPanelBooting` | `admin` | views, translations, livewire, routes |
| `fireClientRoutes()` | `ClientRoutesRegistering` | `client` | views, livewire, routes |
| `fireApiRoutes()` | `ApiRoutesRegistering` | `api` | routes |
| `fireMcpRoutes()` | `McpRoutesRegistering` | `mcp` | routes |
| `fireMcpTools()` | `McpToolsRegistering` | -- | returns handler class names |
| `fireConsoleBooting()` | `ConsoleBooting` | -- | artisan commands |
| `fireQueueWorkerBooting()` | `QueueWorkerBooting` | -- | queue-specific init |

All route-registering fire methods call `refreshRoutes()` afterward to deduplicate names and refresh lookups.

## Default Scan Paths

- `app_path('Core')` -- application-level core modules
- `app_path('Mod')` -- feature modules
- `app_path('Website')` -- domain-scoped website modules
- `src/Core` -- framework's own modules
- `src/Mod` -- framework's own feature modules

Configurable via `config('core.module_paths')`.

## Priority System

- Default: `0`
- Higher values run first: `['onAdmin', 100]` runs before `['onAdmin', 0]`
- Negative values run last: `['onCleanup', -10]`

## Key Integration Points

- `Init::boot()` returns `App\Boot` if it exists, allowing apps to customise providers
- `Boot::basePath()` auto-detects monorepo vs vendor structure
- `LifecycleEventProvider` processes middleware aliases, view namespaces, and Livewire components collected during event dispatch
- Route deduplication prevents `route:cache` failures when the same route file serves multiple domains
