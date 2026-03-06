# Lifecycle Events

Core PHP Framework uses an event-driven architecture where modules declare interest in lifecycle events. This enables lazy loading and modular composition without tight coupling.

## Overview

The lifecycle event system provides extension points throughout the framework's boot process. Modules register listeners for specific events, and are only instantiated when those events fire.

```
Application Boot
     ↓
LifecycleEventProvider fires events
     ↓
LazyModuleListener intercepts events
     ↓
Module instantiated on-demand
     ↓
Event handler executes
     ↓
Module collects requests (routes, menus, etc.)
     ↓
LifecycleEventProvider processes requests
```

## Core Events

### WebRoutesRegistering

**Fired during:** Web route registration (early boot)

**Purpose:** Register public-facing web routes and views

**Use cases:**
- Marketing pages
- Public blog
- Documentation site
- Landing pages

**Example:**

```php
public function onWebRoutes(WebRoutesRegistering $event): void
{
    // Register view namespace
    $event->views('marketing', __DIR__.'/Views');

    // Register routes
    $event->routes(function () {
        Route::get('/', [HomeController::class, 'index'])->name('home');
        Route::get('/pricing', [PricingController::class, 'index'])->name('pricing');
        Route::get('/contact', [ContactController::class, 'index'])->name('contact');
    });

    // Register middleware
    $event->middleware(['web', 'track-visitor']);
}
```

**Available Methods:**
- `views(string $namespace, string $path)` - Register view namespace
- `routes(Closure $callback)` - Register routes
- `middleware(array $middleware)` - Apply middleware to routes

---

### AdminPanelBooting

**Fired during:** Admin panel initialization

**Purpose:** Register admin routes, menus, and dashboard widgets

**Use cases:**
- Admin CRUD interfaces
- Dashboard widgets
- Settings pages
- Admin navigation

**Example:**

```php
public function onAdmin(AdminPanelBooting $event): void
{
    // Register admin routes
    $event->routes(fn () => require __DIR__.'/Routes/admin.php');

    // Register admin menu
    $event->menu(new BlogMenuProvider());

    // Register dashboard widget
    $event->widget(new PostStatsWidget());

    // Register settings page
    $event->settings('blog', BlogSettingsPage::class);
}
```

**Available Methods:**
- `routes(Closure $callback)` - Register admin routes
- `menu(AdminMenuProvider $provider)` - Register menu items
- `widget(DashboardWidget $widget)` - Register dashboard widget
- `settings(string $key, string $class)` - Register settings page

---

### ApiRoutesRegistering

**Fired during:** API route registration

**Purpose:** Register REST API endpoints

**Use cases:**
- RESTful APIs
- Webhooks
- Third-party integrations
- Mobile app backends

**Example:**

```php
public function onApiRoutes(ApiRoutesRegistering $event): void
{
    $event->routes(function () {
        Route::prefix('v1')->group(function () {
            Route::apiResource('posts', PostApiController::class);
            Route::get('posts/{post}/analytics', [PostApiController::class, 'analytics']);
        });
    });

    // API-specific middleware
    $event->middleware(['api', 'auth:sanctum', 'scope:blog:read']);
}
```

**Available Methods:**
- `routes(Closure $callback)` - Register API routes
- `middleware(array $middleware)` - Apply middleware
- `version(string $version)` - Set API version prefix

---

### ClientRoutesRegistering

**Fired during:** Client route registration

**Purpose:** Register authenticated client/dashboard routes

**Use cases:**
- User dashboards
- Account settings
- Client portals
- Authenticated SPA routes

**Example:**

```php
public function onClientRoutes(ClientRoutesRegistering $event): void
{
    $event->views('dashboard', __DIR__.'/Views/Client');

    $event->routes(function () {
        Route::middleware(['auth', 'verified'])->group(function () {
            Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
            Route::get('/account', [AccountController::class, 'show'])->name('account');
            Route::post('/account', [AccountController::class, 'update']);
        });
    });
}
```

**Available Methods:**
- `views(string $namespace, string $path)` - Register view namespace
- `routes(Closure $callback)` - Register routes
- `middleware(array $middleware)` - Apply middleware

---

### ConsoleBooting

**Fired during:** Console kernel initialization

**Purpose:** Register Artisan commands

**Use cases:**
- Custom commands
- Scheduled tasks
- Maintenance scripts
- Data migrations

**Example:**

```php
public function onConsole(ConsoleBooting $event): void
{
    // Register commands
    $event->commands([
        PublishPostCommand::class,
        ImportPostsCommand::class,
        GenerateSitemapCommand::class,
    ]);

    // Register scheduled tasks
    $event->schedule(function (Schedule $schedule) {
        $schedule->command(PublishScheduledPostsCommand::class)
            ->hourly()
            ->withoutOverlapping();

        $schedule->command(GenerateSitemapCommand::class)
            ->daily()
            ->at('01:00');
    });
}
```

**Available Methods:**
- `commands(array $commands)` - Register commands
- `schedule(Closure $callback)` - Define scheduled tasks

---

### McpToolsRegistering

**Fired during:** MCP server initialization

**Purpose:** Register MCP (Model Context Protocol) tools for AI integrations

**Use cases:**
- AI-powered features
- LLM tool integrations
- Automated workflows
- AI assistants

**Example:**

```php
public function onMcpTools(McpToolsRegistering $event): void
{
    $event->tools([
        GetPostTool::class,
        CreatePostTool::class,
        UpdatePostTool::class,
        SearchPostsTool::class,
    ]);

    // Register prompts
    $event->prompts([
        GenerateBlogPostPrompt::class,
    ]);

    // Register resources
    $event->resources([
        BlogPostResource::class,
    ]);
}
```

**Available Methods:**
- `tools(array $tools)` - Register MCP tools
- `prompts(array $prompts)` - Register prompt templates
- `resources(array $resources)` - Register resources

---

### FrameworkBooted

**Fired after:** All other lifecycle events have completed

**Purpose:** Late-stage initialization and cross-module setup

**Use cases:**
- Service registration
- Event listeners
- Observer registration
- Cache warming

**Example:**

```php
public function onFrameworkBooted(FrameworkBooted $event): void
{
    // Register event listeners
    Event::listen(PostPublished::class, SendPostNotification::class);
    Event::listen(PostViewed::class, IncrementViewCount::class);

    // Register model observers
    Post::observe(PostObserver::class);

    // Register service
    app()->singleton(BlogService::class, function ($app) {
        return new BlogService(
            $app->make(PostRepository::class),
            $app->make(CategoryRepository::class)
        );
    });

    // Register policies
    Gate::policy(Post::class, PostPolicy::class);
}
```

**Available Methods:**
- `service(string $abstract, Closure $factory)` - Register service
- `singleton(string $abstract, Closure $factory)` - Register singleton
- `listener(string $event, string $listener)` - Register event listener

## Event Declaration

Modules declare event listeners via the `$listens` property in `Boot.php`:

```php
<?php

namespace Mod\Blog;

use Core\Events\WebRoutesRegistering;
use Core\Events\AdminPanelBooting;
use Core\Events\ApiRoutesRegistering;

class Boot
{
    public static array $listens = [
        WebRoutesRegistering::class => 'onWebRoutes',
        AdminPanelBooting::class => 'onAdmin',
        ApiRoutesRegistering::class => 'onApiRoutes',
    ];

    public function onWebRoutes(WebRoutesRegistering $event): void { }
    public function onAdmin(AdminPanelBooting $event): void { }
    public function onApiRoutes(ApiRoutesRegistering $event): void { }
}
```

## Lazy Loading

Modules are **not** instantiated until an event they listen to is fired:

```php
// Web request → Only WebRoutesRegistering listeners loaded
// API request → Only ApiRoutesRegistering listeners loaded
// Admin request → Only AdminPanelBooting listeners loaded
// Console command → Only ConsoleBooting listeners loaded
```

This dramatically reduces bootstrap time and memory usage.

## Event Flow

### 1. Module Discovery

`ModuleScanner` scans configured paths for `Boot.php` files:

```php
$scanner = new ModuleScanner();
$modules = $scanner->scan([
    app_path('Core'),
    app_path('Mod'),
    app_path('Plug'),
]);
```

### 2. Listener Registration

`ModuleRegistry` wires lazy listeners:

```php
$registry = new ModuleRegistry();
$registry->registerModules($modules);

// Creates LazyModuleListener for each event-module pair
Event::listen(WebRoutesRegistering::class, LazyModuleListener::class);
```

### 3. Event Firing

`LifecycleEventProvider` fires events at appropriate times:

```php
// During route registration
$event = new WebRoutesRegistering();
event($event);
```

### 4. Module Loading

`LazyModuleListener` instantiates module on-demand:

```php
public function handle($event): void
{
    $module = new $this->moduleClass(); // Module instantiated HERE
    $module->{$this->method}($event);
}
```

### 5. Request Collection

Modules collect requests during event handling:

```php
public function onWebRoutes(WebRoutesRegistering $event): void
{
    // Stored in $event->routeRequests
    $event->routes(fn () => require __DIR__.'/Routes/web.php');

    // Stored in $event->viewRequests
    $event->views('blog', __DIR__.'/Views');
}
```

### 6. Request Processing

`LifecycleEventProvider` processes collected requests:

```php
foreach ($event->routeRequests as $request) {
    Route::middleware($request['middleware'])
        ->group($request['callback']);
}
```

## Custom Lifecycle Events

You can create custom lifecycle events by extending `LifecycleEvent`:

```php
<?php

namespace Mod\Commerce\Events;

use Core\Events\LifecycleEvent;

class PaymentProvidersRegistering extends LifecycleEvent
{
    protected array $providers = [];

    public function provider(string $name, string $class): void
    {
        $this->providers[$name] = $class;
    }

    public function getProviders(): array
    {
        return $this->providers;
    }
}
```

Fire the event in your service provider:

```php
$event = new PaymentProvidersRegistering();
event($event);

foreach ($event->getProviders() as $name => $class) {
    PaymentGateway::register($name, $class);
}
```

Modules can listen to your custom event:

```php
public static array $listens = [
    PaymentProvidersRegistering::class => 'onPaymentProviders',
];

public function onPaymentProviders(PaymentProvidersRegistering $event): void
{
    $event->provider('stripe', StripeProvider::class);
}
```

## Event Priorities

Control event listener execution order:

```php
Event::listen(WebRoutesRegistering::class, FirstModule::class, 100);
Event::listen(WebRoutesRegistering::class, SecondModule::class, 50);
Event::listen(WebRoutesRegistering::class, ThirdModule::class, 10);

// Execution order: FirstModule → SecondModule → ThirdModule
```

## Testing Lifecycle Events

Test that modules respond to events correctly:

```php
<?php

namespace Tests\Feature\Mod\Blog;

use Tests\TestCase;
use Core\Events\WebRoutesRegistering;
use Mod\Blog\Boot;

class BlogBootTest extends TestCase
{
    public function test_registers_web_routes(): void
    {
        $event = new WebRoutesRegistering();
        $boot = new Boot();

        $boot->onWebRoutes($event);

        $this->assertNotEmpty($event->routeRequests);
        $this->assertNotEmpty($event->viewRequests);
    }

    public function test_registers_admin_menu(): void
    {
        $event = new AdminPanelBooting();
        $boot = new Boot();

        $boot->onAdmin($event);

        $this->assertNotEmpty($event->menuProviders);
    }
}
```

## Best Practices

### 1. Keep Event Handlers Focused

Each event handler should only register resources related to that lifecycle phase:

```php
// ✅ Good
public function onWebRoutes(WebRoutesRegistering $event): void
{
    $event->views('blog', __DIR__.'/Views');
    $event->routes(fn () => require __DIR__.'/Routes/web.php');
}

// ❌ Bad - service registration belongs in FrameworkBooted
public function onWebRoutes(WebRoutesRegistering $event): void
{
    app()->singleton(BlogService::class, ...);
    $event->routes(fn () => require __DIR__.'/Routes/web.php');
}
```

### 2. Use Dependency Injection

Event handlers receive the event object - use it instead of facades:

```php
// ✅ Good
public function onWebRoutes(WebRoutesRegistering $event): void
{
    $event->routes(function () {
        Route::get('/blog', ...);
    });
}

// ❌ Bad - bypasses event system
public function onWebRoutes(WebRoutesRegistering $event): void
{
    Route::get('/blog', ...);
}
```

### 3. Only Listen to Needed Events

Don't register listeners for events you don't need:

```php
// ✅ Good - API-only module
public static array $listens = [
    ApiRoutesRegistering::class => 'onApiRoutes',
];

// ❌ Bad - unnecessary listeners
public static array $listens = [
    WebRoutesRegistering::class => 'onWebRoutes',
    AdminPanelBooting::class => 'onAdmin',
    ApiRoutesRegistering::class => 'onApiRoutes',
];
```

### 4. Keep Boot.php Lightweight

`Boot.php` should only coordinate - extract complex logic to dedicated classes:

```php
// ✅ Good
public function onAdmin(AdminPanelBooting $event): void
{
    $event->menu(new BlogMenuProvider());
    $event->routes(fn () => require __DIR__.'/Routes/admin.php');
}

// ❌ Bad - too much inline logic
public function onAdmin(AdminPanelBooting $event): void
{
    $event->menu([
        'label' => 'Blog',
        'icon' => 'newspaper',
        'children' => [
            // ... 50 lines of menu configuration
        ],
    ]);
}
```

## Learn More

- [Module System](/architecture/module-system)
- [Lazy Loading](/architecture/lazy-loading)
- [Creating Custom Events](/architecture/custom-events)
