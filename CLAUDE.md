# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
composer test                       # Run all tests (PHPUnit)
composer test -- --filter=Name      # Run single test by name
composer test -- --testsuite=Unit   # Run specific test suite
composer pint                       # Format code with Laravel Pint
./vendor/bin/pint --dirty           # Format only changed files
```

## Coding Standards

- **UK English**: colour, organisation, centre (never American spellings)
- **Strict types**: `declare(strict_types=1);` in every PHP file
- **Type hints**: All parameters and return types required
- **Testing**: PHPUnit with Orchestra Testbench
- **License**: EUPL-1.2

## Architecture

### Event-Driven Module Loading

Modules declare interest in lifecycle events via static `$listens` arrays and are only instantiated when those events fire:

```
LifecycleEventProvider::register()
  └── ModuleScanner::scan()        # Finds Boot.php files with $listens
  └── ModuleRegistry::register()   # Wires LazyModuleListener for each event
```

**Key benefit**: Web requests don't load admin modules; API requests don't load web modules.

### Frontages

Frontages are ServiceProviders in `src/Core/Front/` that fire context-specific lifecycle events:

| Frontage | Event | Middleware | Fires When |
|----------|-------|------------|------------|
| Web | `WebRoutesRegistering` | `web` | Public routes |
| Admin | `AdminPanelBooting` | `admin` | Admin panel |
| Api | `ApiRoutesRegistering` | `api` | REST endpoints |
| Client | `ClientRoutesRegistering` | `client` | Authenticated SaaS |
| Cli | `ConsoleBooting` | - | Artisan commands |
| Mcp | `McpToolsRegistering` | - | MCP tool handlers |
| - | `FrameworkBooted` | - | Late-stage initialisation |

### L1 Packages

Subdirectories under `src/Core/` are self-contained "L1 packages" with their own Boot.php, migrations, tests, and views:

```
src/Core/Activity/        # Activity logging (wraps spatie/laravel-activitylog)
src/Core/Bouncer/         # Security blocking/redirects
src/Core/Cdn/             # CDN integration
src/Core/Config/          # Dynamic configuration
src/Core/Front/           # Frontage system (Web, Admin, Api, Client, Cli, Mcp)
src/Core/Lang/            # Translation system
src/Core/Media/           # Media handling with thumbnail helpers
src/Core/Search/          # Search functionality
src/Core/Seo/             # SEO utilities
```

### Module Pattern

```php
class Boot
{
    public static array $listens = [
        WebRoutesRegistering::class => 'onWebRoutes',
        AdminPanelBooting::class => ['onAdmin', 10],  // With priority
    ];

    public function onWebRoutes(WebRoutesRegistering $event): void
    {
        $event->views('example', __DIR__.'/Views');
        $event->routes(fn () => require __DIR__.'/Routes/web.php');
        $event->livewire('example.widget', ExampleWidget::class);
    }
}
```

### Namespace Mapping

| Path | Namespace |
|------|-----------|
| `src/Core/` | `Core\` |
| `src/Mod/` | `Core\Mod\` |
| `src/Plug/` | `Core\Plug\` |
| `src/Website/` | `Core\Website\` |
| `app/Mod/` | `Mod\` |

### Actions Pattern

Single-purpose business logic classes with static `run()` helper:

```php
use Core\Actions\Action;

class CreateOrder
{
    use Action;

    public function __construct(private OrderService $orders) {}

    public function handle(User $user, array $data): Order
    {
        return $this->orders->create($user, $data);
    }
}

// Usage: CreateOrder::run($user, $validated);
```

### Seeder Ordering

Seeders use PHP attributes for dependency ordering:

```php
use Core\Database\Seeders\Attributes\SeederPriority;
use Core\Database\Seeders\Attributes\SeederAfter;

#[SeederPriority(50)]           // Lower runs first (default 50)
#[SeederAfter(FeatureSeeder::class)]
class PackageSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('packages')) return;  // Guard missing tables
        // ...
    }
}
```

### HLCRF Layout System

Data-driven layouts with five regions (Header, Left, Content, Right, Footer):

```php
use Core\Front\Components\Layout;

$page = Layout::make('HCF')          // Variant: Header-Content-Footer
    ->h(view('header'))
    ->c($content)
    ->f(view('footer'));
```

Variant strings: `C` (content only), `HCF` (standard page), `HLCF` (with sidebar), `HLCRF` (full dashboard).

## Testing

Uses Orchestra Testbench with in-memory SQLite. Tests can live:
- `tests/Feature/` and `tests/Unit/` - main test suites
- `src/Core/{Package}/Tests/` - L1 package co-located tests
- `src/Mod/{Module}/Tests/` - module co-located tests

Test fixtures are in `tests/Fixtures/`.

Base test class provides:
```php
$this->getFixturePath('Mod')  // Returns tests/Fixtures/Mod path
```
