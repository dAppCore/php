# Core PHP Framework

[![Tests](https://github.com/host-uk/core-php/workflows/Tests/badge.svg)](https://github.com/host-uk/core-php/actions)
[![Code Coverage](https://codecov.io/gh/host-uk/core-php/branch/main/graph/badge.svg)](https://codecov.io/gh/host-uk/core-php)
[![Latest Stable Version](https://poser.pugx.org/host-uk/core/v/stable)](https://packagist.org/packages/host-uk/core)
[![License](https://img.shields.io/badge/license-EUPL--1.2-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%5E8.2-8892BF.svg)](https://php.net/)
[![Laravel Version](https://img.shields.io/badge/laravel-%5E11.0%7C%5E12.0-FF2D20.svg)](https://laravel.com)

A modular monolith framework for Laravel with event-driven architecture, lazy module loading, and built-in multi-tenancy.

## Documentation

📚 **[Read the full documentation →](https://core.help/)**

- [Getting Started](https://core.help/guide/getting-started)
- [Installation Guide](https://core.help/guide/installation)
- [Architecture Overview](https://core.help/architecture/lifecycle-events)
- [API Reference](https://core.help/packages/api)
- [Security Guide](https://core.help/security/overview)

## Features

- **Event-driven module system** - Modules declare interest in lifecycle events and are only loaded when needed
- **Lazy loading** - Web requests don't load admin modules, API requests don't load web modules
- **Multi-tenant isolation** - Workspace-scoped data with automatic query filtering
- **Actions pattern** - Single-purpose business logic classes with dependency injection
- **Activity logging** - Built-in audit trails for model changes
- **Seeder auto-discovery** - Automatic ordering via priority and dependency attributes
- **HLCRF Layout System** - Hierarchical composable layouts (Header, Left, Content, Right, Footer)

## Installation

```bash
composer require host-uk/core
```

The service provider will be auto-discovered.

## Quick Start

### Creating a Module

```bash
php artisan make:mod Commerce
```

This creates a module at `app/Mod/Commerce/` with a `Boot.php` entry point:

```php
<?php

namespace Mod\Commerce;

use Core\Events\WebRoutesRegistering;
use Core\Events\AdminPanelBooting;

class Boot
{
    public static array $listens = [
        WebRoutesRegistering::class => 'onWebRoutes',
        AdminPanelBooting::class => 'onAdmin',
    ];

    public function onWebRoutes(WebRoutesRegistering $event): void
    {
        $event->views('commerce', __DIR__.'/Views');
        $event->routes(fn () => require __DIR__.'/Routes/web.php');
    }

    public function onAdmin(AdminPanelBooting $event): void
    {
        $event->routes(fn () => require __DIR__.'/Routes/admin.php');
    }
}
```

### Lifecycle Events

| Event | Purpose |
|-------|---------|
| `WebRoutesRegistering` | Public-facing web routes |
| `AdminPanelBooting` | Admin panel routes and navigation |
| `ApiRoutesRegistering` | REST API endpoints |
| `ClientRoutesRegistering` | Authenticated client routes |
| `ConsoleBooting` | Artisan commands |
| `McpToolsRegistering` | MCP tool handlers |
| `FrameworkBooted` | Late-stage initialisation |

## Core Patterns

### Actions

Extract business logic into testable, reusable classes:

```php
use Core\Actions\Action;

class CreateOrder
{
    use Action;

    public function handle(User $user, array $data): Order
    {
        // Business logic here
        return Order::create($data);
    }
}

// Usage
$order = CreateOrder::run($user, $validated);
```

### Multi-Tenant Isolation

Automatic workspace scoping for models:

```php
use Core\Mod\Tenant\Concerns\BelongsToWorkspace;

class Product extends Model
{
    use BelongsToWorkspace;
}

// Queries are automatically scoped to the current workspace
$products = Product::all();

// workspace_id is auto-assigned on create
$product = Product::create(['name' => 'Widget']);
```

### Activity Logging

Track model changes with minimal setup:

```php
use Core\Activity\Concerns\LogsActivity;

class Order extends Model
{
    use LogsActivity;

    protected array $activityLogAttributes = ['status', 'total'];
}
```

### HLCRF Layout System

Data-driven layouts with infinite nesting:

```php
use Core\Front\Components\Layout;

$page = Layout::make('HCF')
    ->h('<nav>Navigation</nav>')
    ->c('<article>Main content</article>')
    ->f('<footer>Footer</footer>');

echo $page;
```

Variant strings define structure: `HCF` (Header-Content-Footer), `HLCRF` (all five regions), `H[LC]CF` (nested layouts).

See [HLCRF.md](packages/core-php/src/Core/Front/HLCRF.md) for full documentation.

## Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag=core-config
```

Configure module paths in `config/core.php`:

```php
return [
    'module_paths' => [
        app_path('Core'),
        app_path('Mod'),
    ],
];
```

## Artisan Commands

```bash
php artisan make:mod Commerce      # Create a module
php artisan make:website Marketing # Create a website module
php artisan make:plug Stripe       # Create a plugin
```

## Module Structure

```
app/Mod/Commerce/
├── Boot.php           # Module entry point
├── Actions/           # Business logic
├── Models/            # Eloquent models
├── Routes/
│   ├── web.php
│   ├── admin.php
│   └── api.php
├── Views/
├── Migrations/
└── config.php
```

## Documentation

- [Patterns Guide](docs/patterns.md) - Detailed documentation for all framework patterns
- [HLCRF Layout System](packages/core-php/src/Core/Front/HLCRF.md) - Composable layout documentation

## Testing

```bash
composer test
```

## Requirements

- PHP 8.2+
- Laravel 11+

## License

EUPL-1.2 - See [LICENSE](LICENSE) for details.
