# Module System

The module system provides automatic discovery and lazy loading of modules based on lifecycle events. Modules are self-contained units of functionality that can hook into the framework at specific points.

## Overview

Traditional Laravel applications use service providers which are all loaded on every request. The Core module system:

- **Auto-discovers** modules by scanning directories
- **Lazy-loads** modules only when their events fire
- **Caches** module registry for performance
- **Supports** multiple module types (Mod, Plug, Website)

## Creating a Module

### Using Artisan

```bash
# Create a standard module
php artisan make:mod Blog

# Create a website module
php artisan make:website Marketing

# Create a plugin module
php artisan make:plug Stripe
```

### Manual Creation

Create a `Boot.php` file in your module directory:

```php
<?php

namespace Mod\Blog;

use Core\Events\WebRoutesRegistering;
use Core\Events\AdminPanelBooting;
use Core\Events\ConsoleBooting;

class Boot
{
    /**
     * Events this module listens to
     */
    public static array $listens = [
        WebRoutesRegistering::class => 'onWebRoutes',
        AdminPanelBooting::class => 'onAdminPanel',
        ConsoleBooting::class => 'onConsole',
    ];

    /**
     * Register public web routes
     */
    public function onWebRoutes(WebRoutesRegistering $event): void
    {
        $event->views('blog', __DIR__.'/Views');
        $event->routes(fn () => require __DIR__.'/Routes/web.php');
    }

    /**
     * Register admin panel routes and menus
     */
    public function onAdminPanel(AdminPanelBooting $event): void
    {
        $event->menu('blog', [
            'label' => 'Blog',
            'icon' => 'newspaper',
            'route' => 'admin.blog.index',
            'order' => 20,
        ]);

        $event->routes(fn () => require __DIR__.'/Routes/admin.php');
    }

    /**
     * Register console commands
     */
    public function onConsole(ConsoleBooting $event): void
    {
        $event->commands([
            Commands\PublishPostsCommand::class,
            Commands\ImportPostsCommand::class,
        ]);
    }
}
```

## Directory Structure

```
Mod/Blog/
├── Boot.php                    # Module bootstrap
├── Actions/                    # Business logic
│   ├── CreatePost.php
│   ├── UpdatePost.php
│   └── DeletePost.php
├── Controllers/
│   ├── Web/
│   │   └── PostController.php
│   └── Admin/
│       └── PostController.php
├── Models/
│   ├── Post.php
│   └── Category.php
├── Routes/
│   ├── web.php
│   ├── admin.php
│   └── api.php
├── Views/
│   ├── web/
│   └── admin/
├── Database/
│   ├── Migrations/
│   ├── Factories/
│   └── Seeders/
├── Tests/
│   ├── Feature/
│   └── Unit/
└── Lang/
    └── en_GB/
```

## Lifecycle Events

Modules can hook into these lifecycle events:

### WebRoutesRegistering

Register public-facing web routes:

```php
public function onWebRoutes(WebRoutesRegistering $event): void
{
    // Register views
    $event->views('blog', __DIR__.'/Views');

    // Register translations
    $event->lang('blog', __DIR__.'/Lang');

    // Register routes
    $event->routes(function () {
        Route::get('/blog', [PostController::class, 'index']);
        Route::get('/blog/{slug}', [PostController::class, 'show']);
    });
}
```

### AdminPanelBooting

Register admin panel routes, menus, and widgets:

```php
public function onAdminPanel(AdminPanelBooting $event): void
{
    // Register admin menu
    $event->menu('blog', [
        'label' => 'Blog',
        'icon' => 'newspaper',
        'route' => 'admin.blog.index',
        'order' => 20,
        'children' => [
            ['label' => 'Posts', 'route' => 'admin.blog.posts'],
            ['label' => 'Categories', 'route' => 'admin.blog.categories'],
        ],
    ]);

    // Register routes
    $event->routes(fn () => require __DIR__.'/Routes/admin.php');
}
```

### ApiRoutesRegistering

Register REST API endpoints:

```php
public function onApiRoutes(ApiRoutesRegistering $event): void
{
    $event->routes(function () {
        Route::get('/posts', [Api\PostController::class, 'index']);
        Route::post('/posts', [Api\PostController::class, 'store']);
        Route::get('/posts/{id}', [Api\PostController::class, 'show']);
    });
}
```

### ClientRoutesRegistering

Register authenticated client routes:

```php
public function onClientRoutes(ClientRoutesRegistering $event): void
{
    $event->routes(function () {
        Route::get('/dashboard/posts', [Client\PostController::class, 'index']);
        Route::post('/dashboard/posts', [Client\PostController::class, 'store']);
    });
}
```

### ConsoleBooting

Register Artisan commands:

```php
public function onConsole(ConsoleBooting $event): void
{
    $event->commands([
        Commands\PublishPostsCommand::class,
        Commands\GenerateSitemapCommand::class,
    ]);

    $event->schedule(function (Schedule $schedule) {
        $schedule->command('blog:publish-scheduled')
            ->everyFiveMinutes();
    });
}
```

### McpToolsRegistering

Register MCP (Model Context Protocol) tools:

```php
public function onMcpTools(McpToolsRegistering $event): void
{
    $event->tool('blog:create-post', Tools\CreatePostTool::class);
    $event->tool('blog:list-posts', Tools\ListPostsTool::class);
}
```

### FrameworkBooted

Late-stage initialization after all modules loaded:

```php
public function onFrameworkBooted(FrameworkBooted $event): void
{
    // Register macros, observers, policies, etc.
    Post::observe(PostObserver::class);

    Builder::macro('published', function () {
        return $this->where('status', 'published')
            ->where('published_at', '<=', now());
    });
}
```

## Module Discovery

The framework automatically scans these directories:

```php
// config/core.php
'module_paths' => [
    app_path('Core'),      // Core modules
    app_path('Mod'),       // Standard modules
    app_path('Website'),   // Website modules
    app_path('Plug'),      // Plugin modules
],
```

### Custom Namespaces

Map custom paths to namespaces:

```php
use Core\Module\ModuleScanner;

$scanner = app(ModuleScanner::class);
$scanner->setNamespaceMap([
    '/Extensions' => 'Extensions\\',
    '/Custom' => 'Custom\\Modules\\',
]);
```

## Lazy Loading

Modules are only instantiated when their events fire:

1. **Scan Phase** - `ModuleScanner` finds all `Boot.php` files
2. **Registry Phase** - `ModuleRegistry` wires lazy listeners
3. **Event Phase** - Event fires, `LazyModuleListener` instantiates module
4. **Execution Phase** - Module method is called

**Performance Benefits:**
- Modules not used in CLI don't load in CLI
- Admin modules don't load on public requests
- API modules don't load on web requests

## Module Registry

View registered modules and their listeners:

```php
use Core\Module\ModuleRegistry;

$registry = app(ModuleRegistry::class);

// Get all registered modules
$modules = $registry->all();

// Get modules for specific event
$webModules = $registry->forEvent(WebRoutesRegistering::class);
```

## Module Cache

Module discovery is cached for performance:

```bash
# Clear module cache
php artisan cache:clear

# Or specifically
php artisan optimize:clear
```

**Cache Location:** `bootstrap/cache/modules.php`

## Module Dependencies

Modules can declare dependencies using service discovery:

```php
use Core\Service\Contracts\ServiceDefinition;
use Core\Service\Contracts\ServiceDependency;

class Boot implements ServiceDefinition
{
    public static array $listens = [
        WebRoutesRegistering::class => 'onWebRoutes',
    ];

    public function getServiceName(): string
    {
        return 'blog';
    }

    public function getServiceVersion(): string
    {
        return '1.0.0';
    }

    public function getDependencies(): array
    {
        return [
            new ServiceDependency('media', '>=1.0'),
            new ServiceDependency('cdn', '>=2.0'),
        ];
    }
}
```

## Testing Modules

### Feature Tests

```php
<?php

namespace Mod\Blog\Tests\Feature;

use Tests\TestCase;
use Mod\Blog\Actions\CreatePost;

class PostCreationTest extends TestCase
{
    public function test_creates_post(): void
    {
        $post = CreatePost::run([
            'title' => 'Test Post',
            'content' => 'Content here',
        ]);

        $this->assertDatabaseHas('posts', [
            'title' => 'Test Post',
        ]);

        $this->get("/blog/{$post->slug}")
            ->assertOk()
            ->assertSee('Test Post');
    }
}
```

### Unit Tests

```php
<?php

namespace Mod\Blog\Tests\Unit;

use Tests\TestCase;
use Mod\Blog\Boot;
use Core\Events\WebRoutesRegistering;

class BootTest extends TestCase
{
    public function test_registers_web_routes(): void
    {
        $event = new WebRoutesRegistering();
        $boot = new Boot();

        $boot->onWebRoutes($event);

        $this->assertTrue($event->hasRoutes());
    }
}
```

## Best Practices

### 1. Keep Modules Focused

```php
// ✅ Good - focused modules
Mod/Blog/
Mod/Comments/
Mod/Analytics/

// ❌ Bad - monolithic module
Mod/Everything/
```

### 2. Use Proper Namespacing

```php
// ✅ Good
namespace Mod\Blog\Controllers\Web;

// ❌ Bad
namespace App\Http\Controllers;
```

### 3. Register Dependencies

```php
// ✅ Good - declare dependencies
public function getDependencies(): array
{
    return [
        new ServiceDependency('media', '>=1.0'),
    ];
}
```

### 4. Only Hook Necessary Events

```php
// ✅ Good - only web routes
public static array $listens = [
    WebRoutesRegistering::class => 'onWebRoutes',
];

// ❌ Bad - hooks everything
public static array $listens = [
    WebRoutesRegistering::class => 'onWebRoutes',
    AdminPanelBooting::class => 'onAdminPanel',
    ApiRoutesRegistering::class => 'onApiRoutes',
    // ... (when you don't need them all)
];
```

### 5. Use Actions for Business Logic

```php
// ✅ Good
$post = CreatePost::run($data);

// ❌ Bad - logic in controller
public function store(Request $request)
{
    $post = Post::create($request->all());
    event(new PostCreated($post));
    Cache::forget('posts');
    return redirect()->route('posts.show', $post);
}
```

## Learn More

- [Lifecycle Events →](/core/events)
- [Actions Pattern →](/core/actions)
- [Service Discovery →](/core/services)
- [Architecture Overview →](/architecture/module-system)
