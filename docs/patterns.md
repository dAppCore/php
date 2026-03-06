# Core PHP Framework Patterns

This guide covers the key architectural patterns used throughout the Core PHP Framework. Each pattern includes guidance on when to use it, quick start examples, and common pitfalls to avoid.

## Table of Contents

1. [Actions Pattern](#1-actions-pattern)
2. [Multi-Tenant Data Isolation](#2-multi-tenant-data-isolation)
3. [Module System (Lifecycle Events)](#3-module-system-lifecycle-events)
4. [Activity Logging](#4-activity-logging)
5. [Seeder Auto-Discovery](#5-seeder-auto-discovery)
6. [Service Definition](#6-service-definition)

---

## 1. Actions Pattern

**Location:** `packages/core-php/src/Core/Actions/`

Actions are single-purpose classes that encapsulate business logic. They extract complex operations from controllers and Livewire components into testable, reusable units.

### When to Use

- Business operations with multiple steps (validation, authorization, persistence, side effects)
- Logic that should be reusable across controllers, commands, and API endpoints
- Operations that benefit from dependency injection
- Any operation complex enough to warrant unit testing in isolation

### When NOT to Use

- Simple CRUD operations that don't need validation beyond form requests
- One-line operations that don't benefit from abstraction

### Quick Start

```php
<?php

namespace Mod\Example\Actions;

use Core\Actions\Action;

class CreatePost
{
    use Action;

    public function __construct(
        protected PostRepository $posts,
        protected ImageService $images
    ) {}

    public function handle(User $user, array $data): Post
    {
        // Your business logic here
        $post = $this->posts->create([
            'user_id' => $user->id,
            'title' => $data['title'],
            'content' => $data['content'],
        ]);

        if (isset($data['image'])) {
            $this->images->attach($post, $data['image']);
        }

        return $post;
    }
}
```

**Usage:**

```php
// Via dependency injection (preferred)
public function __construct(private CreatePost $createPost) {}

$post = $this->createPost->handle($user, $validated);

// Via static helper
$post = CreatePost::run($user, $validated);

// Via container
$post = app(CreatePost::class)->handle($user, $validated);
```

### Full Example

Here's an Action that creates a project with entitlement checking:

```php
<?php

namespace Mod\Projects\Actions;

use Core\Actions\Action;
use Core\Mod\Tenant\Exceptions\EntitlementException;
use Core\Mod\Tenant\Models\User;
use Core\Mod\Tenant\Models\Workspace;
use Core\Mod\Tenant\Services\EntitlementService;
use Mod\Projects\Models\Project;
use Spatie\Activitylog\Facades\Activity;

class CreateProject
{
    public function __construct(
        protected EntitlementService $entitlements
    ) {}

    /**
     * @throws EntitlementException
     */
    public function handle(User $user, array $data): Project
    {
        $workspace = $user->defaultWorkspace();

        // Check entitlements
        if ($workspace) {
            $this->checkEntitlements($workspace);
        }

        // Generate unique slug if not provided
        $data['slug'] = $data['slug'] ?? $this->generateUniqueSlug($data['name']);
        $data['user_id'] = $user->id;
        $data['workspace_id'] = $data['workspace_id'] ?? $workspace?->id;

        // Create the project
        $project = Project::create($data);

        // Record usage
        if ($workspace) {
            $this->entitlements->recordUsage(
                $workspace,
                'projects.count',
                1,
                $user,
                ['project_id' => $project->id]
            );
        }

        // Log activity
        Activity::causedBy($user)
            ->performedOn($project)
            ->withProperties(['name' => $project->name])
            ->log('created');

        return $project;
    }

    public static function run(User $user, array $data): Project
    {
        return app(static::class)->handle($user, $data);
    }

    protected function checkEntitlements(Workspace $workspace): void
    {
        $result = $this->entitlements->can($workspace, 'projects.count');

        if ($result->isDenied()) {
            throw new EntitlementException(
                "You have reached your project limit. Please upgrade your plan.",
                'projects.count'
            );
        }
    }

    protected function generateUniqueSlug(string $name): string
    {
        $slug = \Illuminate\Support\Str::slug($name);
        $original = $slug;
        $counter = 1;

        while (Project::where('slug', $slug)->exists()) {
            $slug = $original . '-' . $counter++;
        }

        return $slug;
    }
}
```

### Directory Structure

```
Mod/Example/Actions/
├── CreateThing.php
├── UpdateThing.php
├── DeleteThing.php
└── Thing/
    ├── PublishThing.php
    └── ArchiveThing.php
```

### Configuration

Actions use Laravel's service container for dependency injection. No additional configuration is required.

### Common Pitfalls

| Pitfall | Solution |
|---------|----------|
| Too many responsibilities | Keep actions focused on one operation. Split into multiple actions if needed. |
| Returning void | Always return something useful (the created/updated model, a result DTO). |
| Not using dependency injection | Inject dependencies via constructor, not `app()` calls inside methods. |
| Catching all exceptions | Let exceptions bubble up for proper error handling. |
| Direct database queries | Use repositories or model methods for testability. |

---

## 2. Multi-Tenant Data Isolation

**Location:** `packages/core-php/src/Mod/Tenant/`

The multi-tenant system ensures data isolation between workspaces using global scopes and traits. This is a **security-critical** pattern that prevents cross-tenant data leakage.

### When to Use

- Any model that "belongs" to a workspace and should be isolated
- Data that must never be visible across tenant boundaries
- Resources that should be automatically scoped to the current workspace context

### Key Components

| Component | Purpose |
|-----------|---------|
| `BelongsToWorkspace` trait | Add to models that belong to a workspace |
| `WorkspaceScope` | Global scope that filters queries |
| `MissingWorkspaceContextException` | Security exception when context is missing |

### Quick Start

```php
<?php

namespace Mod\Example\Models;

use Illuminate\Database\Eloquent\Model;
use Core\Mod\Tenant\Concerns\BelongsToWorkspace;

class Project extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id',
        'name',
        'description',
    ];
}
```

That's it! The trait automatically:
- Assigns `workspace_id` when creating records
- Scopes all queries to the current workspace
- Throws exceptions if workspace context is missing (in strict mode)
- Invalidates cache when records change

### Full Example

**Model with workspace isolation:**

```php
<?php

namespace Mod\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Core\Mod\Tenant\Concerns\BelongsToWorkspace;

class Product extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id',
        'name',
        'sku',
        'price',
    ];

    // Model methods work normally - scoping is automatic
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
```

**Using the model:**

```php
// Automatically scoped to current workspace
$products = Product::all();
$product = Product::where('sku', 'ABC123')->first();
$activeProducts = Product::active()->get();

// Creating - workspace_id is auto-assigned
$product = Product::create(['name' => 'Widget', 'sku' => 'W001']);

// Cached collection for current workspace
$products = Product::ownedByCurrentWorkspaceCached();

// Query a specific workspace (admin use)
$products = Product::forWorkspace($workspace)->get();

// Query across all workspaces (admin use - be careful!)
$allProducts = Product::acrossWorkspaces()->get();
```

### Strict Mode vs Permissive Mode

**Strict mode (default):** Throws `MissingWorkspaceContextException` when workspace context is unavailable. This is the secure default.

```php
// In strict mode, this throws an exception if no workspace context
$products = Product::all(); // MissingWorkspaceContextException
```

**Permissive mode:** Returns empty results instead of throwing. Use sparingly.

```php
// Disable strict mode globally (not recommended)
WorkspaceScope::disableStrictMode();

// Disable for a specific callback
WorkspaceScope::withoutStrictMode(function () {
    // Operations here return empty results if no context
    $products = Product::all(); // Returns empty collection
});

// Disable for a specific model
class LegacyProduct extends Model
{
    use BelongsToWorkspace;

    protected bool $workspaceScopeStrict = false; // Not recommended
}
```

### When to Use `forWorkspace()` vs `acrossWorkspaces()`

| Method | Use Case |
|--------|----------|
| `forWorkspace($workspace)` | Admin viewing a specific workspace's data |
| `acrossWorkspaces()` | Global reports, admin dashboards, CLI commands |
| Neither (default) | Normal application code - uses current context |

**Always prefer the default scoping** unless you have a specific reason to query across workspaces.

### Security Considerations

1. **Never disable strict mode globally** in production
2. **Audit uses of `acrossWorkspaces()`** - each use should be justified
3. **CLI commands** automatically bypass strict mode (they have no request context)
4. **Test data isolation** - write tests that verify cross-tenant queries fail
5. **The `workspace_id` column must exist** on all tenant-scoped tables

### Configuration

```php
// In migrations - always add workspace_id
Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    // ...
    $table->index('workspace_id'); // Important for performance
});
```

### Common Pitfalls

| Pitfall | Solution |
|---------|----------|
| Forgetting `workspace_id` in migrations | Always add the foreign key and index |
| Using `acrossWorkspaces()` casually | Audit every usage, prefer default scoping |
| Suppressing the exception | Don't catch `MissingWorkspaceContextException` - fix the context |
| Direct DB queries | Use Eloquent models so scopes apply |
| Joining across tenant boundaries | Ensure joins respect workspace_id |

---

## 3. Module System (Lifecycle Events)

**Location:** `packages/core-php/src/Core/Events/`, `packages/core-php/src/Core/Module/`

The module system uses lifecycle events for lazy-loading modules. Modules declare what events they listen to, and are only instantiated when those events fire.

### When to Use

- Creating a new feature module
- Registering routes, views, Livewire components
- Adding admin panel navigation
- Registering console commands

### How It Works

```
Application Start
       │
       ▼
ModuleScanner scans for Boot.php files with $listens arrays
       │
       ▼
ModuleRegistry registers lazy listeners for each event-module pair
       │
       ▼
Request comes in → Appropriate lifecycle event fires
       │
       ▼
LazyModuleListener instantiates module and calls handler
       │
       ▼
Module registers its resources (routes, views, etc.)
```

### Quick Start

Create a `Boot.php` file in your module directory:

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

### Available Lifecycle Events

| Event | Context | When It Fires |
|-------|---------|---------------|
| `WebRoutesRegistering` | Web requests | Public frontend routes |
| `AdminPanelBooting` | Admin requests | Admin panel setup |
| `ApiRoutesRegistering` | API requests | REST API routes |
| `ClientRoutesRegistering` | Client dashboard | Authenticated client routes |
| `ConsoleBooting` | CLI commands | Artisan booting |
| `QueueWorkerBooting` | Queue workers | Queue worker starting |
| `McpToolsRegistering` | MCP server | MCP tool registration |
| `FrameworkBooted` | All contexts | After all context-specific events |

### Full Example

A complete module Boot class:

```php
<?php

namespace Mod\Inventory;

use Core\Events\AdminPanelBooting;
use Core\Events\ApiRoutesRegistering;
use Core\Events\ConsoleBooting;
use Core\Events\WebRoutesRegistering;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class Boot extends ServiceProvider
{
    protected string $moduleName = 'inventory';

    public static array $listens = [
        AdminPanelBooting::class => 'onAdminPanel',
        ApiRoutesRegistering::class => 'onApiRoutes',
        WebRoutesRegistering::class => 'onWebRoutes',
        ConsoleBooting::class => 'onConsole',
    ];

    public function register(): void
    {
        // Register service bindings
        $this->app->singleton(
            Services\InventoryService::class,
            Services\InventoryService::class
        );
    }

    public function boot(): void
    {
        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/Migrations');
        $this->loadTranslationsFrom(__DIR__.'/Lang/en', 'inventory');
    }

    public function onAdminPanel(AdminPanelBooting $event): void
    {
        $event->views($this->moduleName, __DIR__.'/View/Blade');

        // Navigation
        $event->navigation([
            'label' => 'Inventory',
            'icon' => 'box',
            'route' => 'admin.inventory.index',
            'sort_order' => 50,
        ]);

        // Livewire components
        $event->livewire('inventory-list', View\Livewire\InventoryList::class);
        $event->livewire('product-form', View\Livewire\ProductForm::class);
    }

    public function onApiRoutes(ApiRoutesRegistering $event): void
    {
        $event->routes(fn () => Route::middleware('api')
            ->prefix('v1/inventory')
            ->group(__DIR__.'/Routes/api.php'));
    }

    public function onWebRoutes(WebRoutesRegistering $event): void
    {
        $event->views($this->moduleName, __DIR__.'/View/Blade');
        $event->routes(fn () => Route::middleware('web')
            ->group(__DIR__.'/Routes/web.php'));
    }

    public function onConsole(ConsoleBooting $event): void
    {
        $event->command(Console\SyncInventoryCommand::class);
        $event->command(Console\PruneStaleStockCommand::class);
    }
}
```

### Available Request Methods

Events provide these methods for registering resources:

| Method | Purpose |
|--------|---------|
| `routes(callable)` | Register route files/callbacks |
| `views(namespace, path)` | Register view namespaces |
| `livewire(alias, class)` | Register Livewire components |
| `middleware(alias, class)` | Register middleware aliases |
| `command(class)` | Register Artisan commands |
| `translations(namespace, path)` | Register translation namespaces |
| `bladeComponentPath(path, namespace)` | Register anonymous Blade components |
| `policy(model, policy)` | Register model policies |
| `navigation(item)` | Register navigation items |

### The Request/Collect Pattern

Events use a "request/collect" pattern:

1. **Modules request** resources via methods like `routes()`, `views()`
2. **Requests are collected** in arrays during event dispatch
3. **LifecycleEventProvider processes** all requests with validation

This ensures modules don't directly mutate infrastructure and allows central validation.

### Module Directory Structure

```
Mod/Inventory/
├── Boot.php                    # Module entry point
├── Routes/
│   ├── web.php
│   └── api.php
├── View/
│   ├── Blade/                  # Blade templates
│   │   └── admin/
│   └── Livewire/               # Livewire components
├── Models/
├── Actions/
├── Services/
├── Console/
├── Migrations/
└── Lang/
```

### Configuration

Namespace detection is automatic based on path:
- `/Core` paths → `Core\` namespace
- `/Mod` paths → `Mod\` namespace
- `/Website` paths → `Website\` namespace
- `/Plug` paths → `Plug\` namespace

### Common Pitfalls

| Pitfall | Solution |
|---------|----------|
| Registering routes outside events | Always use `$event->routes()` in handlers |
| Heavy work in `$listens` handlers | Keep handlers lightweight; defer work |
| Forgetting to add `$listens` | Module won't load without static `$listens` |
| Using wrong event | Match event to request context (web, api, admin) |
| Instantiating in `$listens` | The array is read statically; don't call methods |

---

## 4. Activity Logging

**Location:** `packages/core-php/src/Core/Activity/`

Activity logging tracks model changes and user actions. Built on spatie/laravel-activitylog with workspace-aware enhancements.

### When to Use

- Audit trails for compliance
- User activity history
- Model change tracking
- Debugging and support

### Quick Start

Add the trait to any model:

```php
<?php

namespace Mod\Example\Models;

use Illuminate\Database\Eloquent\Model;
use Core\Activity\Concerns\LogsActivity;

class Document extends Model
{
    use LogsActivity;

    protected $fillable = ['title', 'content', 'status'];
}
```

That's it! All creates, updates, and deletes are now logged with:
- Changed attributes (old and new values)
- The user who made the change
- The workspace context
- Timestamp

### Full Example

**Model with customized logging:**

```php
<?php

namespace Mod\Contracts\Models;

use Illuminate\Database\Eloquent\Model;
use Core\Activity\Concerns\LogsActivity;
use Spatie\Activitylog\Contracts\Activity;

class Contract extends Model
{
    use LogsActivity;

    protected $fillable = [
        'workspace_id',
        'client_name',
        'value',
        'status',
        'signed_at',
        'internal_notes', // Don't log this
    ];

    // Only log these attributes
    protected array $activityLogAttributes = [
        'client_name',
        'value',
        'status',
        'signed_at',
    ];

    // Custom log name
    protected string $activityLogName = 'contracts';

    // Events to log (default: created, updated, deleted)
    protected array $activityLogEvents = ['created', 'updated', 'deleted'];

    // Add custom data to activity
    public function customizeActivity(Activity $activity, string $eventName): void
    {
        $activity->properties = $activity->properties->merge([
            'contract_number' => $this->contract_number,
            'client_id' => $this->client_id,
        ]);
    }
}
```

**Querying activity logs:**

```php
use Core\Activity\Services\ActivityLogService;

$service = app(ActivityLogService::class);

// Get activities for a specific model
$activities = $service->logFor($contract)->recent(20);

// Get activities by a user in a workspace
$activities = $service
    ->logBy($user)
    ->forWorkspace($workspace)
    ->lastDays(7)
    ->paginate(25);

// Get all "updated" events for a model type
$activities = $service
    ->forSubjectType(Contract::class)
    ->ofType('updated')
    ->between('2024-01-01', '2024-12-31')
    ->get();

// Search activities
$results = $service->search('approved contract');

// Get statistics
$stats = $service->statistics($workspace);
// Returns: ['total' => 150, 'by_event' => [...], 'by_subject' => [...], 'by_user' => [...]]
```

**Using the Activity model directly:**

```php
use Core\Activity\Models\Activity;

// Get activities for a workspace
$activities = Activity::forWorkspace($workspace)->newest()->get();

// Filter by event type
$created = Activity::createdEvents()->lastDays(30)->get();
$deleted = Activity::deletedEvents()->withDeletedSubject()->get();

// Get activities with changes
$withChanges = Activity::withChanges()->get();

// Access change details
foreach ($activities as $activity) {
    echo $activity->description;
    echo $activity->causer_name;  // "John Doe" or "System"
    echo $activity->subject_name; // "Contract #123"

    // Get specific changes
    foreach ($activity->changes as $field => $values) {
        echo "{$field}: {$values['old']} -> {$values['new']}";
    }
}
```

### Configuration

Configure via model properties:

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `$activityLogAttributes` | array | null (all) | Attributes to log |
| `$activityLogName` | string | 'default' | Log name for grouping |
| `$activityLogEvents` | array | ['created', 'updated', 'deleted'] | Events to log |
| `$activityLogWorkspace` | bool | true | Include workspace_id |
| `$activityLogOnlyDirty` | bool | true | Only log changed attributes |

**Global configuration (config/core.php):**

```php
'activity' => [
    'enabled' => env('ACTIVITY_LOG_ENABLED', true),
    'log_name' => 'default',
    'include_workspace' => true,
    'default_events' => ['created', 'updated', 'deleted'],
    'retention_days' => 90,
],
```

### Temporarily Disabling Logging

```php
// Disable for a callback
Document::withoutActivityLogging(function () {
    $document->update(['status' => 'processed']);
});

// Check if logging is enabled
if (Document::activityLoggingEnabled()) {
    // ...
}
```

### Pruning Old Logs

```bash
# Via artisan command
php artisan activity:prune --days=90

# Via service
$service = app(ActivityLogService::class);
$deleted = $service->prune(90); // Delete logs older than 90 days
```

### Common Pitfalls

| Pitfall | Solution |
|---------|----------|
| Logging sensitive data | Exclude sensitive fields via `$activityLogAttributes` |
| Excessive logging | Log only meaningful changes, not every field |
| Performance issues | Use `withoutActivityLogging()` for bulk operations |
| Missing workspace context | Ensure workspace is set before logging |
| Large properties | Limit logged data; don't log file contents |

---

## 5. Seeder Auto-Discovery

**Location:** `packages/core-php/src/Core/Database/Seeders/`

The seeder auto-discovery system scans module directories for seeders and executes them in the correct order based on priority and dependencies.

### When to Use

- Creating seeders for a module
- Seeding reference data (features, packages, configuration)
- Establishing dependencies between seeders
- Demo/test data setup

### Quick Start

Create a seeder in your module's `Database/Seeders/` directory:

```php
<?php

namespace Mod\Example\Database\Seeders;

use Illuminate\Database\Seeder;

class ExampleFeatureSeeder extends Seeder
{
    // Optional: Lower values run first (default: 50)
    public int $priority = 10;

    public function run(): void
    {
        // Seed your data
        Feature::updateOrCreate(
            ['code' => 'example.feature'],
            ['name' => 'Example Feature', 'type' => 'boolean']
        );
    }
}
```

Seeders are discovered automatically - no manual registration required.

### Priority and Dependencies

**Using priority (simple ordering):**

```php
// Using class property
class FeatureSeeder extends Seeder
{
    public int $priority = 10; // Runs early
}

// Using attribute
use Core\Database\Seeders\Attributes\SeederPriority;

#[SeederPriority(10)]
class FeatureSeeder extends Seeder
{
    public function run(): void { /* ... */ }
}
```

**Priority guidelines:**

| Range | Use Case |
|-------|----------|
| 0-20 | Foundation (features, config) |
| 20-40 | Core data (packages, workspaces) |
| 40-60 | Default (general seeders) |
| 60-80 | Content (pages, posts) |
| 80-100 | Demo/test data |

**Using dependencies (explicit ordering):**

```php
use Core\Database\Seeders\Attributes\SeederAfter;
use Core\Database\Seeders\Attributes\SeederBefore;
use Core\Mod\Tenant\Database\Seeders\FeatureSeeder;

// This seeder runs AFTER FeatureSeeder completes
#[SeederAfter(FeatureSeeder::class)]
class PackageSeeder extends Seeder
{
    public function run(): void
    {
        // Can safely reference features created by FeatureSeeder
    }
}

// This seeder runs BEFORE PackageSeeder
#[SeederBefore(PackageSeeder::class)]
class FeatureSeeder extends Seeder
{
    public function run(): void
    {
        // Features are created first
    }
}
```

**Multiple dependencies:**

```php
#[SeederAfter(FeatureSeeder::class, PackageSeeder::class)]
class WorkspaceSeeder extends Seeder
{
    // Runs after both FeatureSeeder and PackageSeeder
}
```

### Full Example

```php
<?php

namespace Mod\Billing\Database\Seeders;

use Core\Database\Seeders\Attributes\SeederAfter;
use Core\Database\Seeders\Attributes\SeederPriority;
use Core\Mod\Tenant\Database\Seeders\FeatureSeeder;
use Core\Mod\Tenant\Database\Seeders\WorkspaceSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Mod\Billing\Models\Plan;

#[SeederPriority(30)]
#[SeederAfter(FeatureSeeder::class)]
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        // Guard against missing table
        if (! Schema::hasTable('billing_plans')) {
            return;
        }

        $plans = [
            [
                'code' => 'free',
                'name' => 'Free',
                'price_monthly' => 0,
                'features' => ['basic.access'],
                'sort_order' => 1,
            ],
            [
                'code' => 'pro',
                'name' => 'Professional',
                'price_monthly' => 29,
                'features' => ['basic.access', 'advanced.features', 'support.priority'],
                'sort_order' => 2,
            ],
            [
                'code' => 'enterprise',
                'name' => 'Enterprise',
                'price_monthly' => 99,
                'features' => ['basic.access', 'advanced.features', 'support.priority', 'api.access'],
                'sort_order' => 3,
            ],
        ];

        foreach ($plans as $planData) {
            Plan::updateOrCreate(
                ['code' => $planData['code']],
                $planData
            );
        }

        $this->command->info('Billing plans seeded successfully.');
    }
}
```

### How Discovery Works

1. `SeederDiscovery` scans configured paths for `*Seeder.php` files
2. Files are read to extract namespace and class name
3. Priority and dependency attributes/properties are parsed
4. Seeders are topologically sorted (dependencies first, then by priority)
5. `CoreDatabaseSeeder` executes them in order

### Configuration

The discovery scans these paths by default:
- `app/Core/*/Database/Seeders/`
- `app/Mod/*/Database/Seeders/`
- `packages/core-php/src/Core/*/Database/Seeders/`
- `packages/core-php/src/Mod/*/Database/Seeders/`

### Common Pitfalls

| Pitfall | Solution |
|---------|----------|
| Circular dependencies | Review dependencies; use priority instead if possible |
| Missing table errors | Check `Schema::hasTable()` before seeding |
| Non-idempotent seeders | Use `updateOrCreate()` or `firstOrCreate()` |
| Hardcoded IDs | Use codes/slugs for lookups, not numeric IDs |
| Dependencies on non-existent seeders | Ensure dependent seeders are discoverable |
| Forgetting to output progress | Use `$this->command->info()` for visibility |

---

## 6. Service Definition

**Location:** `packages/core-php/src/Core/Service/`

Services are the product layer of the framework. They define how modules are presented as SaaS products with versioning, health checks, and dependency management.

### When to Use

- Defining a new SaaS product/service
- Adding health monitoring to a service
- Declaring service dependencies
- Managing service lifecycle (deprecation, sunset)

### Key Components

| Component | Purpose |
|-----------|---------|
| `ServiceDefinition` | Interface for service definitions |
| `ServiceVersion` | Semantic versioning with deprecation |
| `ServiceDependency` | Declare dependencies on other services |
| `HealthCheckable` | Interface for health monitoring |
| `HealthCheckResult` | Health check response object |

### Quick Start

```php
<?php

namespace Mod\Billing;

use Core\Service\Contracts\ServiceDefinition;
use Core\Service\ServiceVersion;

class BillingService implements ServiceDefinition
{
    public static function definition(): array
    {
        return [
            'code' => 'billing',
            'module' => 'Mod\\Billing',
            'name' => 'Billing',
            'tagline' => 'Subscription management',
            'icon' => 'credit-card',
            'color' => '#10B981',
            'entitlement_code' => 'core.srv.billing',
            'sort_order' => 20,
        ];
    }

    public static function version(): ServiceVersion
    {
        return new ServiceVersion(1, 0, 0);
    }

    public static function dependencies(): array
    {
        return [];
    }

    // From AdminMenuProvider interface
    public function menuItems(): array
    {
        return [
            [
                'label' => 'Billing',
                'icon' => 'credit-card',
                'route' => 'admin.billing.index',
            ],
        ];
    }
}
```

### Full Example

A complete service with health checks and dependencies:

```php
<?php

namespace Mod\Analytics;

use Core\Service\Contracts\HealthCheckable;
use Core\Service\Contracts\ServiceDefinition;
use Core\Service\Contracts\ServiceDependency;
use Core\Service\HealthCheckResult;
use Core\Service\ServiceVersion;

class AnalyticsService implements ServiceDefinition, HealthCheckable
{
    public function __construct(
        protected ClickHouseConnection $clickhouse,
        protected RedisConnection $redis
    ) {}

    public static function definition(): array
    {
        return [
            'code' => 'analytics',
            'module' => 'Mod\\Analytics',
            'name' => 'AnalyticsHost',
            'tagline' => 'Privacy-first website analytics',
            'description' => 'Lightweight, GDPR-compliant analytics without cookies.',
            'icon' => 'chart-line',
            'color' => '#F59E0B',
            'entitlement_code' => 'core.srv.analytics',
            'sort_order' => 30,
        ];
    }

    public static function version(): ServiceVersion
    {
        return new ServiceVersion(2, 1, 0);
    }

    public static function dependencies(): array
    {
        return [
            ServiceDependency::required('auth', '>=1.0.0'),
            ServiceDependency::optional('billing'),
        ];
    }

    public function healthCheck(): HealthCheckResult
    {
        try {
            $start = microtime(true);

            // Check ClickHouse connection
            $this->clickhouse->select('SELECT 1');

            // Check Redis connection
            $this->redis->ping();

            $responseTime = (microtime(true) - $start) * 1000;

            if ($responseTime > 1000) {
                return HealthCheckResult::degraded(
                    'Database responding slowly',
                    ['response_time_ms' => $responseTime]
                );
            }

            return HealthCheckResult::healthy(
                'All systems operational',
                [],
                $responseTime
            );
        } catch (\Exception $e) {
            return HealthCheckResult::fromException($e);
        }
    }

    public function menuItems(): array
    {
        return [
            [
                'label' => 'Analytics',
                'icon' => 'chart-line',
                'route' => 'admin.analytics.dashboard',
                'children' => [
                    ['label' => 'Dashboard', 'route' => 'admin.analytics.dashboard'],
                    ['label' => 'Sites', 'route' => 'admin.analytics.sites'],
                    ['label' => 'Reports', 'route' => 'admin.analytics.reports'],
                ],
            ],
        ];
    }
}
```

### Service Versioning

```php
use Core\Service\ServiceVersion;

// Create a version
$version = new ServiceVersion(2, 1, 0);
echo $version; // "2.1.0"

// Parse from string
$version = ServiceVersion::fromString('v2.1.0');

// Check compatibility
$minimum = new ServiceVersion(1, 5, 0);
$current = new ServiceVersion(1, 8, 2);
$current->isCompatibleWith($minimum); // true

// Mark as deprecated
$version = (new ServiceVersion(1, 0, 0))
    ->deprecate(
        'Use v2.x instead. See docs/migration.md',
        new \DateTimeImmutable('2025-06-01')
    );

// Check deprecation status
if ($version->deprecated) {
    echo $version->deprecationMessage;
}

if ($version->isPastSunset()) {
    throw new ServiceSunsetException('Service no longer available');
}
```

### Service Dependencies

```php
use Core\Service\Contracts\ServiceDependency;

public static function dependencies(): array
{
    return [
        // Required with minimum version
        ServiceDependency::required('auth', '>=1.0.0'),

        // Required with version range
        ServiceDependency::required('billing', '>=2.0.0', '<3.0.0'),

        // Optional dependency
        ServiceDependency::optional('analytics'),
    ];
}
```

### Health Checks

```php
use Core\Service\Contracts\HealthCheckable;
use Core\Service\HealthCheckResult;

class MyService implements ServiceDefinition, HealthCheckable
{
    public function healthCheck(): HealthCheckResult
    {
        // Healthy
        return HealthCheckResult::healthy('All systems operational');

        // Healthy with response time
        return HealthCheckResult::healthy(
            'Service operational',
            ['connections' => 5],
            responseTimeMs: 45.2
        );

        // Degraded (works but with issues)
        return HealthCheckResult::degraded(
            'High latency detected',
            ['latency_ms' => 1500]
        );

        // Unhealthy
        return HealthCheckResult::unhealthy(
            'Database connection failed',
            ['last_error' => 'Connection refused']
        );

        // From exception
        try {
            $this->checkCriticalSystem();
        } catch (\Exception $e) {
            return HealthCheckResult::fromException($e);
        }
    }
}
```

### Health Check Guidelines

Health checks should be:

| Guideline | Recommendation |
|-----------|----------------|
| Fast | Complete within 5 seconds (< 1 second preferred) |
| Non-destructive | Read-only operations only |
| Representative | Test actual critical dependencies |
| Safe | Handle all exceptions, return HealthCheckResult |

### Configuration

Service definitions populate the `platform_services` table:

```php
// definition() return array
[
    'code' => 'billing',          // Unique identifier (required)
    'module' => 'Mod\\Billing',   // Module namespace (required)
    'name' => 'Billing',          // Display name (required)
    'tagline' => 'Subscription management',  // Short description
    'description' => '...',       // Full description
    'icon' => 'link',             // FontAwesome icon
    'color' => '#3B82F6',         // Brand color
    'entitlement_code' => '...',  // Access control code
    'sort_order' => 10,           // Menu ordering
]
```

### Common Pitfalls

| Pitfall | Solution |
|---------|----------|
| Slow health checks | Keep under 1 second; test critical paths only |
| Circular dependencies | Review service architecture; refactor if needed |
| Missing version() | Always implement; return `ServiceVersion::initial()` at minimum |
| Health check exceptions | Catch all exceptions; return `fromException()` |
| Forgetting dependencies | Document all service interdependencies |

---

## Summary

These patterns form the backbone of the Core PHP Framework:

| Pattern | Purpose | Key Files |
|---------|---------|-----------|
| Actions | Encapsulate business logic | `Core\Actions\Action` |
| Multi-Tenant | Data isolation between workspaces | `BelongsToWorkspace`, `WorkspaceScope` |
| Module System | Lazy-loading via lifecycle events | `Boot.php`, `$listens` |
| Activity Logging | Audit trail and change tracking | `LogsActivity`, `ActivityLogService` |
| Seeder Discovery | Auto-discovered, ordered seeding | `#[SeederPriority]`, `#[SeederAfter]` |
| Service Definition | SaaS product layer | `ServiceDefinition`, `HealthCheckable` |

For more details, explore the source files in their respective locations or check the inline documentation.
