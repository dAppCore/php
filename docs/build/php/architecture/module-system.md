# Module System

Core PHP Framework uses a modular monolith architecture where features are organized into self-contained modules that communicate through events and contracts.

## What is a Module?

A module is a self-contained feature with its own:

- Routes (web, admin, API)
- Models and migrations
- Controllers and actions
- Views and assets
- Configuration
- Tests

Modules declare their lifecycle event interests and are only loaded when needed.

## Module Types

### Core Modules (`app/Core/`)

Foundation modules that provide framework functionality:

```
app/Core/
├── Events/          # Lifecycle events
├── Module/          # Module system
├── Actions/         # Actions pattern
├── Config/          # Configuration system
├── Media/           # Media handling
└── Storage/         # Cache and storage
```

**Namespace:** `Core\`

**Purpose:** Framework internals, shared utilities

### Feature Modules (`app/Mod/`)

Business domain modules:

```
app/Mod/
├── Tenant/          # Multi-tenancy
├── Commerce/        # E-commerce features
├── Blog/            # Blogging
└── Analytics/       # Analytics
```

**Namespace:** `Mod\`

**Purpose:** Application features

### Website Modules (`app/Website/`)

Site-specific implementations:

```
app/Website/
├── Marketing/       # Marketing site
├── Docs/            # Documentation site
└── Support/         # Support portal
```

**Namespace:** `Website\`

**Purpose:** Deployable websites/frontends

### Plugin Modules (`app/Plug/`)

Optional integrations:

```
app/Plug/
├── Stripe/          # Stripe integration
├── Mailchimp/       # Mailchimp integration
└── Analytics/       # Analytics integrations
```

**Namespace:** `Plug\`

**Purpose:** Third-party integrations, optional features

## Module Structure

Standard module structure created by `php artisan make:mod`:

```
app/Mod/Example/
├── Boot.php                    # Module entry point
├── config.php                  # Module configuration
│
├── Actions/                    # Business logic
│   ├── CreateExample.php
│   └── UpdateExample.php
│
├── Controllers/                # HTTP controllers
│   ├── Admin/
│   │   └── ExampleController.php
│   └── ExampleController.php
│
├── Models/                     # Eloquent models
│   └── Example.php
│
├── Migrations/                 # Database migrations
│   └── 2026_01_01_create_examples_table.php
│
├── Database/
│   ├── Factories/              # Model factories
│   │   └── ExampleFactory.php
│   └── Seeders/                # Database seeders
│       └── ExampleSeeder.php
│
├── Routes/                     # Route definitions
│   ├── web.php                 # Public routes
│   ├── admin.php               # Admin routes
│   └── api.php                 # API routes
│
├── Views/                      # Blade templates
│   ├── index.blade.php
│   └── show.blade.php
│
├── Requests/                   # Form requests
│   ├── StoreExampleRequest.php
│   └── UpdateExampleRequest.php
│
├── Resources/                  # API resources
│   └── ExampleResource.php
│
├── Policies/                   # Authorization policies
│   └── ExamplePolicy.php
│
├── Events/                     # Domain events
│   └── ExampleCreated.php
│
├── Listeners/                  # Event listeners
│   └── SendExampleNotification.php
│
├── Jobs/                       # Queued jobs
│   └── ProcessExample.php
│
├── Services/                   # Domain services
│   └── ExampleService.php
│
├── Mcp/                        # MCP tools
│   └── Tools/
│       └── GetExampleTool.php
│
└── Tests/                      # Module tests
    ├── Feature/
    │   └── ExampleTest.php
    └── Unit/
        └── ExampleServiceTest.php
```

## Creating Modules

### Using Artisan Commands

```bash
# Create a feature module
php artisan make:mod Blog

# Create a website module
php artisan make:website Marketing

# Create a plugin module
php artisan make:plug Stripe
```

### Manual Creation

1. Create directory structure
2. Create `Boot.php` with `$listens` array
3. Register lifecycle event handlers

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

## Module Discovery

### Auto-Discovery

Modules are automatically discovered by scanning configured paths:

```php
// config/core.php
'module_paths' => [
    app_path('Core'),
    app_path('Mod'),
    app_path('Plug'),
],
```

### Manual Registration

Disable auto-discovery and register modules explicitly:

```php
// config/core.php
'modules' => [
    'auto_discover' => false,
],

// app/Providers/AppServiceProvider.php
use Core\Module\ModuleRegistry;

public function boot(): void
{
    $registry = app(ModuleRegistry::class);

    $registry->register(Mod\Blog\Boot::class);
    $registry->register(Mod\Commerce\Boot::class);
}
```

## Module Configuration

### Module-Level Configuration

Each module can have a `config.php` file:

```php
<?php
// app/Mod/Blog/config.php

return [
    'posts_per_page' => env('BLOG_POSTS_PER_PAGE', 12),
    'enable_comments' => env('BLOG_COMMENTS_ENABLED', true),
    'cache_duration' => env('BLOG_CACHE_DURATION', 3600),
];
```

Access configuration:

```php
$perPage = config('mod.blog.posts_per_page', 12);
```

### Publishing Configuration

Allow users to customize module configuration:

```php
// app/Mod/Blog/BlogServiceProvider.php
public function boot(): void
{
    $this->publishes([
        __DIR__.'/config.php' => config_path('mod/blog.php'),
    ], 'blog-config');
}
```

Users can then publish and customize:

```bash
php artisan vendor:publish --tag=blog-config
```

## Inter-Module Communication

### 1. Events (Recommended)

Modules communicate via domain events:

```php
// Mod/Blog/Events/PostPublished.php
class PostPublished
{
    public function __construct(public Post $post) {}
}

// Mod/Blog/Actions/PublishPost.php
PostPublished::dispatch($post);

// Mod/Analytics/Listeners/TrackPostPublished.php
Event::listen(PostPublished::class, TrackPostPublished::class);
```

### 2. Service Contracts

Define contracts for shared functionality:

```php
// Core/Contracts/NotificationService.php
interface NotificationService
{
    public function send(Notifiable $notifiable, Notification $notification): void;
}

// Mod/Email/EmailNotificationService.php
class EmailNotificationService implements NotificationService
{
    public function send(Notifiable $notifiable, Notification $notification): void
    {
        // Implementation
    }
}

// Register in service provider
app()->bind(NotificationService::class, EmailNotificationService::class);

// Use in other modules
app(NotificationService::class)->send($user, $notification);
```

### 3. Facades

Create facades for frequently used services:

```php
// Mod/Blog/Facades/Blog.php
class Blog extends Facade
{
    protected static function getFacadeAccessor()
    {
        return BlogService::class;
    }
}

// Usage
Blog::getRecentPosts(10);
Blog::findBySlug('example-post');
```

## Module Dependencies

### Declaring Dependencies

Use PHP attributes to declare module dependencies:

```php
<?php

namespace Mod\BlogComments;

use Core\Module\Attributes\RequiresModule;

#[RequiresModule(Mod\Blog\Boot::class)]
class Boot
{
    // ...
}
```

### Checking Dependencies

Verify dependencies are met:

```php
use Core\Module\ModuleRegistry;

$registry = app(ModuleRegistry::class);

if ($registry->isLoaded(Mod\Blog\Boot::class)) {
    // Blog module is available
}
```

## Module Isolation

### Database Isolation

Use workspace scoping for multi-tenant isolation:

```php
use Core\Mod\Tenant\Concerns\BelongsToWorkspace;

class Post extends Model
{
    use BelongsToWorkspace;
}

// Queries automatically scoped to current workspace
Post::all(); // Only returns posts for current workspace
```

### Cache Isolation

Use workspace-scoped caching:

```php
use Core\Mod\Tenant\Concerns\HasWorkspaceCache;

class Post extends Model
{
    use BelongsToWorkspace, HasWorkspaceCache;
}

// Cache isolated per workspace
Post::forWorkspaceCached($workspace, 600);
```

### Route Isolation

Separate route files by context:

```php
// Routes/web.php - Public routes
Route::get('/blog', [BlogController::class, 'index']);

// Routes/admin.php - Admin routes
Route::resource('posts', PostController::class);

// Routes/api.php - API routes
Route::apiResource('posts', PostApiController::class);
```

## Module Testing

### Feature Tests

Test module functionality end-to-end:

```php
<?php

namespace Tests\Feature\Mod\Blog;

use Tests\TestCase;
use Mod\Blog\Models\Post;

class PostTest extends TestCase
{
    public function test_can_view_published_posts(): void
    {
        Post::factory()->published()->count(3)->create();

        $response = $this->get('/blog');

        $response->assertStatus(200);
        $response->assertViewHas('posts');
    }
}
```

### Unit Tests

Test module services and actions:

```php
<?php

namespace Tests\Unit\Mod\Blog;

use Tests\TestCase;
use Mod\Blog\Actions\PublishPost;
use Mod\Blog\Models\Post;

class PublishPostTest extends TestCase
{
    public function test_publishes_post(): void
    {
        $post = Post::factory()->create(['published_at' => null]);

        PublishPost::run($post);

        $this->assertNotNull($post->fresh()->published_at);
    }
}
```

### Module Isolation Tests

Test that module doesn't leak dependencies:

```php
public function test_module_works_without_optional_dependencies(): void
{
    // Simulate missing optional module
    app()->forgetInstance(Mod\Analytics\AnalyticsService::class);

    $response = $this->get('/blog');

    $response->assertStatus(200);
}
```

## Best Practices

### 1. Keep Modules Focused

Each module should have a single, well-defined responsibility:

```
✅ Good: Mod\Blog (blogging features)
✅ Good: Mod\Comments (commenting system)
❌ Bad: Mod\BlogAndCommentsAndTags (too broad)
```

### 2. Use Explicit Dependencies

Don't assume other modules exist:

```php
// ✅ Good
if (class_exists(Mod\Analytics\AnalyticsService::class)) {
    app(AnalyticsService::class)->track($event);
}

// ❌ Bad
app(AnalyticsService::class)->track($event); // Crashes if not available
```

### 3. Avoid Circular Dependencies

```
✅ Good: Blog → Comments (one-way)
❌ Bad: Blog ⟷ Comments (circular)
```

### 4. Use Interfaces for Contracts

Define interfaces for inter-module communication:

```php
// Core/Contracts/SearchProvider.php
interface SearchProvider
{
    public function search(string $query): Collection;
}

// Mod/Blog/BlogSearchProvider.php
class BlogSearchProvider implements SearchProvider
{
    // Implementation
}
```

### 5. Version Your APIs

If modules expose APIs, version them:

```php
// Routes/api.php
Route::prefix('v1')->group(function () {
    Route::apiResource('posts', V1\PostController::class);
});

Route::prefix('v2')->group(function () {
    Route::apiResource('posts', V2\PostController::class);
});
```

## Troubleshooting

### Module Not Loading

Check module is in configured path:

```bash
# Verify path exists
ls -la app/Mod/YourModule

# Check Boot.php exists
cat app/Mod/YourModule/Boot.php

# Verify $listens array
grep "listens" app/Mod/YourModule/Boot.php
```

### Routes Not Registered

Ensure event handler calls `$event->routes()`:

```php
public function onWebRoutes(WebRoutesRegistering $event): void
{
    // Don't forget this!
    $event->routes(fn () => require __DIR__.'/Routes/web.php');
}
```

### Views Not Found

Register view namespace:

```php
public function onWebRoutes(WebRoutesRegistering $event): void
{
    // Register view namespace
    $event->views('blog', __DIR__.'/Views');
}
```

Then use namespaced views:

```php
return view('blog::index'); // Not just 'index'
```

## Learn More

- [Lifecycle Events](/architecture/lifecycle-events)
- [Lazy Loading](/architecture/lazy-loading)
- [Multi-Tenancy](/patterns-guide/multi-tenancy)
- [Actions Pattern](/patterns-guide/actions)
