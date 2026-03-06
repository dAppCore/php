# Getting Started

Welcome to the Core PHP Framework! This guide will help you understand what the framework is, when to use it, and how to get started.

## What is Core PHP?

Core PHP is a **modular monolith framework** for Laravel that provides:

- **Event-driven architecture** - Modules communicate via lifecycle events
- **Lazy loading** - Only load what you need when you need it
- **Multi-tenant isolation** - Built-in workspace scoping
- **Action patterns** - Testable, reusable business logic
- **Activity logging** - Audit trails out of the box

## When to Use Core PHP

### ✅ Good Fit

- **Multi-tenant SaaS applications** - Built-in workspace isolation
- **Growing monoliths** - Need structure without microservices complexity
- **Modular applications** - Clear module boundaries with lazy loading
- **API-first applications** - Comprehensive API package with OpenAPI docs

### ❌ Not a Good Fit

- **Simple CRUD apps** - May be overkill for basic applications
- **Existing large codebases** - Migration would be significant effort
- **Need for polyglot services** - Better suited for monolithic PHP apps

## Architecture Overview

```
┌─────────────────────────────────────────────┐
│         Application Bootstrap               │
├─────────────────────────────────────────────┤
│      LifecycleEventProvider                 │
│   (fires WebRoutesRegistering, etc.)        │
└──────────────┬──────────────────────────────┘
               │
       ┌───────▼────────┐
       │  ModuleRegistry │
       │  (lazy loading) │
       └───────┬─────────┘
               │
       ┌───────▼────────────────┐
       │   Module Boot Classes   │
       │ • Mod/Commerce/Boot.php │
       │ • Mod/Billing/Boot.php  │
       │ • Mod/Analytics/Boot.php│
       └─────────────────────────┘
```

Modules declare which events they're interested in:

```php
class Boot
{
    public static array $listens = [
        WebRoutesRegistering::class => 'onWebRoutes',
        AdminPanelBooting::class => 'onAdmin',
    ];
}
```

The framework only instantiates modules when their events fire.

## Core Concepts

### 1. Lifecycle Events

Events fired during application bootstrap:

- `WebRoutesRegistering` - Public web routes
- `AdminPanelBooting` - Admin panel
- `ApiRoutesRegistering` - REST API
- `ClientRoutesRegistering` - Authenticated client routes
- `ConsoleBooting` - Artisan commands
- `FrameworkBooted` - Late initialization

### 2. Module System

Modules are self-contained feature bundles:

```
app/Mod/Commerce/
├── Boot.php           # Module entry point
├── Actions/           # Business logic
├── Models/            # Eloquent models
├── Routes/            # Route files
├── Views/             # Blade templates
├── Migrations/        # Database migrations
└── config.php         # Module configuration
```

### 3. Workspace Scoping

All tenant data is automatically scoped:

```php
use Core\Mod\Tenant\Concerns\BelongsToWorkspace;

class Product extends Model
{
    use BelongsToWorkspace;
}

// Automatically filtered to current workspace
$products = Product::all();
```

### 4. Actions Pattern

Single-purpose business logic:

```php
use Core\Actions\Action;

class CreateOrder
{
    use Action;

    public function handle(User $user, array $data): Order
    {
        // Business logic here
    }
}

// Usage
$order = CreateOrder::run($user, $validated);
```

## Next Steps

- [Installation →](./installation)
- [Configuration →](./configuration)
- [Quick Start →](./quick-start)

## Requirements

- **PHP** 8.2 or higher
- **Laravel** 11 or 12
- **Database** MySQL 8.0+, PostgreSQL 13+, or SQLite 3.35+
- **Composer** 2.0+

## Support

- 📖 [Documentation](https://docs.example.com)
- 💬 [GitHub Discussions](https://github.com/host-uk/core-php/discussions)
- 🐛 [Issue Tracker](https://github.com/host-uk/core-php/issues)
- 📧 [Email Support](mailto:support@host.uk.com)
