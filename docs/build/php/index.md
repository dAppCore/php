# PHP Framework

The PHP framework provides the foundation for Host UK applications including the module system, lifecycle events, multi-tenancy, and shared utilities.

## Installation

```bash
composer require host-uk/core
```

## Quick Start

```php
<?php

namespace Mod\Example;

use Core\Events\WebRoutesRegistering;

class Boot
{
    public static array $listens = [
        WebRoutesRegistering::class => 'onWebRoutes',
    ];

    public function onWebRoutes(WebRoutesRegistering $event): void
    {
        $event->views('example', __DIR__.'/Views');
        $event->routes(fn () => require __DIR__.'/Routes/web.php');
    }
}
```

## Key Features

### Foundation

- **[Module System](/core/modules)** - Auto-discover and lazy-load modules based on lifecycle events
- **[Lifecycle Events](/core/events)** - Event-driven extension points throughout the framework
- **[Actions Pattern](/core/actions)** - Single-purpose business logic classes
- **[Service Discovery](/core/services)** - Automatic service registration and dependency management

### Multi-Tenancy

- **[Workspaces & Namespaces](/core/tenancy)** - Workspace and namespace scoping for data isolation
- **[Workspace Caching](/core/tenancy#workspace-caching)** - Isolated cache management per workspace
- **[Context Resolution](/core/tenancy#context-resolution)** - Automatic workspace/namespace detection

### Data & Storage

- **[Configuration Management](/core/configuration)** - Multi-profile configuration with versioning and export/import
- **[Activity Logging](/core/activity)** - Track changes to models with automatic workspace scoping
- **[Seeder Discovery](/core/seeders)** - Automatic seeder discovery with dependency ordering
- **[CDN Integration](/core/cdn)** - Unified CDN interface for BunnyCDN and Cloudflare

### Content & Media

- **[Media Processing](/core/media)** - Image optimization, responsive images, and thumbnails
- **[Search](/core/search)** - Unified search interface across modules with analytics
- **[SEO Tools](/core/seo)** - SEO metadata generation, sitemaps, and structured data

### Security

- **[Security Headers](/core/security)** - Configurable security headers with CSP support
- **[Email Shield](/core/email-shield)** - Disposable email detection and validation
- **[Action Gate](/core/action-gate)** - Permission-based action authorization
- **[Blocklist Service](/core/security#blocklist)** - IP blocklist and rate limiting

### Utilities

- **[Input Sanitization](/core/security#sanitization)** - XSS protection and input cleaning
- **[Encryption](/core/security#encryption)** - Additional encryption utilities (HadesEncrypt)
- **[Translation Memory](/core/i18n)** - Translation management with fuzzy matching and ICU support

## Architecture

The Core package follows a modular monolith architecture with:

1. **Event-Driven Loading** - Modules are lazy-loaded based on lifecycle events
2. **Dependency Injection** - All services are resolved through Laravel's container
3. **Trait-Based Features** - Common functionality provided via traits (e.g., `LogsActivity`, `BelongsToWorkspace`)
4. **Multi-Tenancy First** - Workspace scoping is built into the foundation

## Artisan Commands

```bash
# Module Management
php artisan make:mod Blog
php artisan make:website Marketing
php artisan make:plug Stripe

# Configuration
php artisan config:export production
php artisan config:import production.json
php artisan config:version

# Maintenance
php artisan activity:prune --days=90
php artisan email-shield:prune --days=30
php artisan cache:warm

# SEO
php artisan seo:generate-sitemap
php artisan seo:audit-canonical
php artisan seo:test-structured-data

# Storage
php artisan storage:offload --disk=public
```

## Configuration

```php
// config/core.php
return [
    'module_paths' => [
        app_path('Core'),
        app_path('Mod'),
        app_path('Plug'),
    ],

    'modules' => [
        'auto_discover' => true,
        'cache_enabled' => true,
    ],

    'seeders' => [
        'auto_discover' => true,
        'paths' => [
            'Mod/*/Database/Seeders',
            'Core/*/Database/Seeders',
        ],
    ],

    'activity' => [
        'enabled' => true,
        'retention_days' => 90,
        'log_ip_address' => false,
    ],

    'workspace_cache' => [
        'enabled' => true,
        'ttl' => 3600,
        'use_tags' => true,
    ],
];
```

[View full configuration options →](/guide/configuration#core-configuration)

## Events

Core package dispatches these lifecycle events:

- `Core\Events\WebRoutesRegistering` - Public web routes
- `Core\Events\AdminPanelBooting` - Admin panel initialization
- `Core\Events\ApiRoutesRegistering` - REST API routes
- `Core\Events\ClientRoutesRegistering` - Authenticated client routes
- `Core\Events\ConsoleBooting` - Artisan commands
- `Core\Events\McpToolsRegistering` - MCP tools
- `Core\Events\FrameworkBooted` - Late-stage initialization

[Learn more about Lifecycle Events →](/core/events)

## Middleware

- `Core\Mod\Tenant\Middleware\RequireWorkspaceContext` - Ensure workspace is set
- `Core\Headers\SecurityHeaders` - Apply security headers
- `Core\Bouncer\BlocklistService` - IP blocklist
- `Core\Bouncer\Gate\ActionGateMiddleware` - Action authorization

## Global Helpers

```php
// Get current workspace
$workspace = workspace();

// Create activity log
activity()
    ->performedOn($model)
    ->log('action');

// Generate CDN URL
$url = cdn_url('path/to/asset.jpg');

// Get CSP nonce
$nonce = csp_nonce();
```

## Best Practices

### 1. Use Actions for Business Logic

```php
// ✅ Good
$post = CreatePost::run($data);

// ❌ Bad
$post = Post::create($data);
event(new PostCreated($post));
Cache::forget('posts');
```

### 2. Log Activity for Audit Trail

```php
class Post extends Model
{
    use LogsActivity;

    protected array $activityLogAttributes = ['title', 'status', 'published_at'];
}
```

### 3. Use Workspace Scoping

```php
class Post extends Model
{
    use BelongsToWorkspace;
}
```

### 4. Leverage Module System

```php
// Create focused modules with clear boundaries
Mod/Blog/
Mod/Commerce/
Mod/Analytics/
```

## Testing

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use Mod\Blog\Actions\CreatePost;

class CreatePostTest extends TestCase
{
    public function test_creates_post(): void
    {
        $post = CreatePost::run([
            'title' => 'Test Post',
            'content' => 'Test content',
        ]);

        $this->assertDatabaseHas('posts', [
            'title' => 'Test Post',
        ]);
    }
}
```

## Changelog

See [CHANGELOG.md](https://github.com/host-uk/core-php/blob/main/packages/core-php/changelog/2026/jan/features.md)

## License

EUPL-1.2

## Learn More

- [Module System →](/core/modules)
- [Lifecycle Events →](/core/events)
- [Multi-Tenancy →](/core/tenancy)
- [Configuration →](/core/configuration)
- [Activity Logging →](/core/activity)
