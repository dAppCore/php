# Lazy Loading

Core PHP Framework uses lazy loading to defer module instantiation until absolutely necessary. This dramatically improves performance by only loading code relevant to the current request.

## How It Works

### Traditional Approach (Everything Loads)

```php
// Boot ALL modules on every request
$modules = [
    new BlogModule(),
    new CommerceModule(),
    new AnalyticsModule(),
    new AdminModule(),
    new ApiModule(),
    // ... dozens more
];

// Web request loads admin code it doesn't need
// API request loads web views it doesn't use
// Memory: ~50MB, Boot time: ~500ms
```

### Lazy Loading Approach (On-Demand)

```php
// Register listeners WITHOUT instantiating modules
Event::listen(WebRoutesRegistering::class, LazyModuleListener::for(BlogModule::class));
Event::listen(AdminPanelBooting::class, LazyModuleListener::for(AdminModule::class));

// Web request → Only BlogModule instantiated
// API request → Only ApiModule instantiated
// Memory: ~15MB, Boot time: ~150ms
```

## Architecture

### 1. Module Discovery

`ModuleScanner` finds modules and extracts their event interests:

```php
$modules = [
    [
        'class' => Mod\Blog\Boot::class,
        'listens' => [
            WebRoutesRegistering::class => 'onWebRoutes',
            AdminPanelBooting::class => 'onAdmin',
        ],
    ],
    // ...
];
```

### 2. Lazy Listener Registration

`ModuleRegistry` creates lazy listeners for each event-module pair:

```php
foreach ($modules as $module) {
    foreach ($module['listens'] as $event => $method) {
        Event::listen($event, new LazyModuleListener(
            $module['class'],
            $method
        ));
    }
}
```

### 3. Event-Driven Loading

When an event fires, `LazyModuleListener` instantiates the module:

```php
class LazyModuleListener
{
    public function __construct(
        private string $moduleClass,
        private string $method,
    ) {}

    public function handle($event): void
    {
        // Module instantiated HERE, not before
        $module = new $this->moduleClass();
        $module->{$this->method}($event);
    }
}
```

## Request Types and Loading

### Web Request

```
Request: GET /blog
      ↓
WebRoutesRegistering fired
      ↓
Only modules listening to WebRoutesRegistering loaded:
  - BlogModule
  - MarketingModule
      ↓
Admin/API modules never instantiated
```

### Admin Request

```
Request: GET /admin/posts
      ↓
AdminPanelBooting fired
      ↓
Only modules with admin routes loaded:
  - BlogAdminModule
  - CoreAdminModule
      ↓
Public web modules never instantiated
```

### API Request

```
Request: GET /api/v1/posts
      ↓
ApiRoutesRegistering fired
      ↓
Only modules with API endpoints loaded:
  - BlogApiModule
  - AuthModule
      ↓
Web/Admin views never loaded
```

### Console Command

```
Command: php artisan blog:publish
      ↓
ConsoleBooting fired
      ↓
Only modules with commands loaded:
  - BlogModule (has blog:publish command)
      ↓
Web/Admin/API routes never registered
```

## Performance Impact

### Memory Usage

| Request Type | Traditional | Lazy Loading | Savings |
|--------------|-------------|--------------|---------|
| Web          | 50 MB       | 15 MB        | 70%     |
| Admin        | 50 MB       | 18 MB        | 64%     |
| API          | 50 MB       | 12 MB        | 76%     |
| Console      | 50 MB       | 10 MB        | 80%     |

### Boot Time

| Request Type | Traditional | Lazy Loading | Savings |
|--------------|-------------|--------------|---------|
| Web          | 500ms       | 150ms        | 70%     |
| Admin        | 500ms       | 180ms        | 64%     |
| API          | 500ms       | 120ms        | 76%     |
| Console      | 500ms       | 100ms        | 80%     |

*Measurements from production application with 50+ modules*

## Selective Loading

### Only Listen to Needed Events

Don't register for events you don't need:

```php
// ✅ Good - API-only module
class Boot
{
    public static array $listens = [
        ApiRoutesRegistering::class => 'onApiRoutes',
    ];
}

// ❌ Bad - unnecessary listeners
class Boot
{
    public static array $listens = [
        WebRoutesRegistering::class => 'onWebRoutes',  // Not needed
        AdminPanelBooting::class => 'onAdmin',         // Not needed
        ApiRoutesRegistering::class => 'onApiRoutes',
    ];
}
```

### Conditional Loading

Load features conditionally within event handlers:

```php
public function onWebRoutes(WebRoutesRegistering $event): void
{
    // Only load blog if enabled
    if (config('modules.blog.enabled')) {
        $event->routes(fn () => require __DIR__.'/Routes/web.php');
    }
}
```

## Deferred Service Providers

Combine with Laravel's deferred providers for maximum laziness:

```php
<?php

namespace Mod\Blog;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Support\DeferrableProvider;

class BlogServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register(): void
    {
        $this->app->singleton(BlogService::class, function ($app) {
            return new BlogService(
                $app->make(PostRepository::class)
            );
        });
    }

    public function provides(): array
    {
        // Only load this provider when BlogService is requested
        return [BlogService::class];
    }
}
```

## Lazy Collections

Use lazy collections for memory-efficient data processing:

```php
// ✅ Good - lazy loading
Post::query()
    ->published()
    ->cursor() // Returns lazy collection
    ->each(function ($post) {
        ProcessPost::dispatch($post);
    });

// ❌ Bad - loads all into memory
Post::query()
    ->published()
    ->get() // Loads everything
    ->each(function ($post) {
        ProcessPost::dispatch($post);
    });
```

## Lazy Relationships

Defer relationship loading until needed:

```php
// ✅ Good - lazy eager loading
$posts = Post::all();

if ($needsComments) {
    $posts->load('comments');
}

// ❌ Bad - always loads comments
$posts = Post::with('comments')->get();
```

## Route Lazy Loading

Laravel 11+ supports route file lazy loading:

```php
// routes/web.php
Route::middleware('web')->group(function () {
    // Only load blog routes when /blog is accessed
    Route::prefix('blog')->group(base_path('routes/blog.php'));
});
```

## Cache Warming

Warm caches during deployment, not during requests:

```bash
# Deploy script
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Modules discovered once, cached
php artisan core:cache-modules
```

## Monitoring Lazy Loading

### Track Module Loading

Log when modules are instantiated:

```php
class LazyModuleListener
{
    public function handle($event): void
    {
        $start = microtime(true);

        $module = new $this->moduleClass();
        $module->{$this->method}($event);

        $duration = (microtime(true) - $start) * 1000;

        Log::debug("Module loaded", [
            'module' => $this->moduleClass,
            'event' => get_class($event),
            'duration_ms' => round($duration, 2),
        ]);
    }
}
```

### Analyze Module Usage

Track which modules load for different request types:

```bash
# Enable debug logging
APP_DEBUG=true LOG_LEVEL=debug

# Make requests and check logs
tail -f storage/logs/laravel.log | grep "Module loaded"
```

## Debugging Lazy Loading

### Force Load All Modules

Disable lazy loading for debugging:

```php
// config/core.php
'modules' => [
    'lazy_loading' => env('MODULES_LAZY_LOADING', true),
],

// .env
MODULES_LAZY_LOADING=false
```

### Check Module Load Order

```php
Event::listen('*', function ($eventName, $data) {
    if (str_starts_with($eventName, 'Core\\Events\\')) {
        Log::debug("Event fired", ['event' => $eventName]);
    }
});
```

### Verify Listeners Registered

```bash
php artisan event:list | grep "Core\\Events"
```

## Best Practices

### 1. Keep Boot.php Lightweight

Move heavy initialization to service providers:

```php
// ✅ Good - lightweight Boot.php
public function onWebRoutes(WebRoutesRegistering $event): void
{
    $event->routes(fn () => require __DIR__.'/Routes/web.php');
}

// ❌ Bad - heavy initialization in Boot.php
public function onWebRoutes(WebRoutesRegistering $event): void
{
    // Don't do this in event handlers!
    $this->registerServices();
    $this->loadViews();
    $this->publishAssets();
    $this->registerCommands();

    $event->routes(fn () => require __DIR__.'/Routes/web.php');
}
```

### 2. Avoid Global State in Modules

Don't store state in module classes:

```php
// ✅ Good - stateless
class Boot
{
    public function onWebRoutes(WebRoutesRegistering $event): void
    {
        $event->routes(fn () => require __DIR__.'/Routes/web.php');
    }
}

// ❌ Bad - stateful
class Boot
{
    private array $config = [];

    public function onWebRoutes(WebRoutesRegistering $event): void
    {
        $this->config = config('blog'); // Don't store state
        $event->routes(fn () => require __DIR__.'/Routes/web.php');
    }
}
```

### 3. Use Dependency Injection

Let the container handle dependencies:

```php
// ✅ Good - DI in services
class BlogService
{
    public function __construct(
        private PostRepository $posts,
        private CacheManager $cache,
    ) {}
}

// ❌ Bad - manual instantiation
class BlogService
{
    public function __construct()
    {
        $this->posts = new PostRepository();
        $this->cache = new CacheManager();
    }
}
```

### 4. Defer Heavy Operations

Don't perform expensive operations during boot:

```php
// ✅ Good - defer to queue
public function onFrameworkBooted(FrameworkBooted $event): void
{
    dispatch(new WarmBlogCache())->afterResponse();
}

// ❌ Bad - expensive operation during boot
public function onFrameworkBooted(FrameworkBooted $event): void
{
    // Don't do this!
    $posts = Post::with('comments', 'categories', 'tags')->get();
    Cache::put('blog:all-posts', $posts, 3600);
}
```

## Advanced Patterns

### Lazy Singletons

Register services as lazy singletons:

```php
$this->app->singleton(BlogService::class, function ($app) {
    return new BlogService(
        $app->make(PostRepository::class)
    );
});
```

Service only instantiated when first requested:

```php
// BlogService not instantiated yet
$posts = Post::all();

// BlogService instantiated HERE
app(BlogService::class)->getRecentPosts();
```

### Contextual Binding

Bind different implementations based on context:

```php
$this->app->when(ApiController::class)
    ->needs(PostRepository::class)
    ->give(CachedPostRepository::class);

$this->app->when(AdminController::class)
    ->needs(PostRepository::class)
    ->give(LivePostRepository::class);
```

### Module Proxies

Create proxies for optional modules:

```php
class AnalyticsProxy
{
    public function track(string $event, array $data = []): void
    {
        // Only load analytics module if it exists
        if (class_exists(Mod\Analytics\AnalyticsService::class)) {
            app(AnalyticsService::class)->track($event, $data);
        }
    }
}
```

## Learn More

- [Module System](/architecture/module-system)
- [Lifecycle Events](/architecture/lifecycle-events)
- [Performance Optimization](/architecture/performance)
