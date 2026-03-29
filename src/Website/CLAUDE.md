# Website -- Domain-Scoped Module System

`Core\Website` provides lazy-loading of website modules based on the incoming HTTP domain. Only the matching provider is registered for a given request, isolating errors so one broken website does not take down others.

## Namespace

`Core\Website` -- autoloaded from `src/Website/`.

## How It Works

### Web Requests

1. `Website\Boot::register()` reads `$_SERVER['HTTP_HOST']`
2. Dispatches a `DomainResolving` event with the hostname
3. Website modules listening for `DomainResolving` check if the host matches their `$domains` patterns
4. The first matching provider is registered via `$app->register()`
5. Only that one provider boots -- all others are skipped

### CLI Context (artisan, tests, queues)

All website providers are loaded via `registerAllProviders()`, which scans `app/Mod/*/Boot.php` for website module Boot classes. This ensures artisan commands, seeders, and queue workers can access all website modules.

## Files

### Boot.php (ServiceProvider)

- Registers `DomainResolver` as singleton
- Web: fires `DomainResolving` event, registers matched provider
- CLI: loads all providers from `app/Mod/`
- Provider load order: must come before `Front\Boot` so listeners are wired before frontage events fire

### DomainResolver

Utility for working with domain patterns. Website Boot classes declare domains as regex patterns:

```php
class Boot extends ServiceProvider
{
    public static array $domains = [
        '/^example\.(com|test)$/',
    ];
}
```

**Methods:**

| Method | Purpose |
|--------|---------|
| `extractDomains(providerClass)` | Read `$domains` static property via reflection |
| `domainsFor(providerClass)` | Convert regex patterns to concrete domain strings. In local env, filters to `.test`/`.localhost` only. |

Pattern expansion handles:
- Fixed domains: `example.com`
- TLD alternatives: `example\.(com|test)` -> `example.com`, `example.test`
- Optional www prefix: `(www\.)?example\.com`

## Writing a Website Module

Website modules live in `app/Mod/{Name}/` (not `src/Mod/`). They use the same `$listens` pattern as regular modules:

```php
namespace Mod\MyWebsite;

class Boot extends ServiceProvider
{
    public static array $domains = [
        '/^mysite\.(com|test)$/',
    ];

    public static array $listens = [
        WebRoutesRegistering::class => 'onWebRoutes',
    ];

    public function onWebRoutes(WebRoutesRegistering $event): void
    {
        $event->routes(fn () => require __DIR__.'/Routes/web.php');
        $event->views('mysite', __DIR__.'/Views');
    }
}
```

## Key Design Decisions

- **Isolation**: One broken website module cannot crash others since only the matched provider loads
- **Lazy loading**: Website modules are not instantiated unless their domain matches
- **Dual paths**: Framework-bundled modules in `src/Mod/`, application modules in `app/Mod/`
- **Environment-aware**: Local dev only serves `.test`/`.localhost` domains from `domainsFor()`
