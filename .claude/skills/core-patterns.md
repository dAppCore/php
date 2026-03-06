---
name: core-patterns
description: Scaffold Core PHP Framework patterns (Actions, Multi-tenant, Activity Logging, Modules, Seeders)
---

# Core Patterns Scaffolding

You are helping the user scaffold common Core PHP Framework patterns. This is an interactive skill - gather information through conversation before generating code.

## Start by asking what the user wants to create

Present these options:

1. **Action class** - Single-purpose business logic class
2. **Multi-tenant model** - Add workspace isolation to a model
3. **Activity logging** - Add change tracking to a model
4. **Module** - Create a new module with Boot class
5. **Seeder** - Create a seeder with dependency ordering

Ask: "What would you like to scaffold? (1-5 or describe what you need)"

---

## Option 1: Action Class

Actions are small, focused classes that do one thing well. They extract complex logic from controllers and Livewire components.

### Gather information

Ask the user for:
- **Action name** (e.g., `CreateInvoice`, `PublishPost`, `SendNotification`)
- **Module** (e.g., `Billing`, `Content`, `Notification`)
- **What it does** (brief description to understand parameters needed)

### Generate the Action

Location: `packages/core-php/src/Mod/{Module}/Actions/{ActionName}.php`

```php
<?php

declare(strict_types=1);

namespace Core\Mod\{Module}\Actions;

use Core\Actions\Action;

/**
 * {Description}
 *
 * Usage:
 *   $action = app({ActionName}::class);
 *   $result = $action->handle($param1, $param2);
 *
 *   // Or via static helper:
 *   $result = {ActionName}::run($param1, $param2);
 */
class {ActionName}
{
    use Action;

    public function __construct(
        // Inject dependencies here
    ) {}

    /**
     * Execute the action.
     */
    public function handle(/* parameters */): mixed
    {
        // Implementation
    }
}
```

### Key points to explain

- Actions use the `Core\Actions\Action` trait for the static `run()` helper
- Dependencies are constructor-injected
- The `handle()` method contains the business logic
- Can optionally implement `Core\Actions\Actionable` for type-hinting
- Naming convention: verb + noun (CreateThing, UpdateThing, DeleteThing)

---

## Option 2: Multi-tenant Model

The `BelongsToWorkspace` trait enforces workspace isolation with automatic scoping and caching.

### Gather information

Ask the user for:
- **Model name** (e.g., `Invoice`, `Project`)
- **Whether workspace context is always required** (default: yes)

### Migration requirement

Ensure the model's table has a `workspace_id` column:

```php
$table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
```

### Add the trait

```php
<?php

declare(strict_types=1);

namespace Core\Mod\{Module}\Models;

use Core\Mod\Tenant\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class {ModelName} extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id',
        // other fields...
    ];

    // Optional: Disable strict mode (not recommended)
    // protected bool $workspaceContextRequired = false;
}
```

### Key points to explain

- **Auto-assignment**: `workspace_id` is automatically set from the current workspace context on create
- **Query scoping**: Use `Model::ownedByCurrentWorkspace()` to scope queries
- **Caching**: Use `Model::ownedByCurrentWorkspaceCached()` for cached collections
- **Security**: Throws `MissingWorkspaceContextException` if no workspace context and strict mode is enabled
- **Relation**: Provides `workspace()` belongsTo relationship

### Usage examples

```php
// Query scoped to current workspace
$invoices = Invoice::ownedByCurrentWorkspace()->where('status', 'paid')->get();

// Cached collection for current workspace
$invoices = Invoice::ownedByCurrentWorkspaceCached();

// Query for specific workspace
$invoices = Invoice::forWorkspace($workspace)->get();

// Check ownership
if ($invoice->belongsToCurrentWorkspace()) {
    // safe to display
}
```

---

## Option 3: Activity Logging

The `LogsActivity` trait wraps spatie/laravel-activitylog with framework defaults and workspace tagging.

### Gather information

Ask the user for:
- **Model name** to add logging to
- **Which attributes to log** (all, or specific ones)
- **Which events to log** (created, updated, deleted - default: all)

### Add the trait

```php
<?php

declare(strict_types=1);

namespace Core\Mod\{Module}\Models;

use Core\Activity\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class {ModelName} extends Model
{
    use LogsActivity;

    // Optional configuration via properties:

    // Log only specific attributes (default: all)
    // protected array $activityLogAttributes = ['status', 'amount'];

    // Custom log name (default: from config)
    // protected string $activityLogName = 'invoices';

    // Events to log (default: created, updated, deleted)
    // protected array $activityLogEvents = ['created', 'updated'];

    // Include workspace_id in properties (default: true)
    // protected bool $activityLogWorkspace = true;

    // Only log dirty attributes (default: true)
    // protected bool $activityLogOnlyDirty = true;
}
```

### Custom activity tap (optional)

```php
/**
 * Customize activity before saving.
 */
protected function customizeActivity(\Spatie\Activitylog\Contracts\Activity $activity, string $eventName): void
{
    $activity->properties = $activity->properties->merge([
        'custom_field' => $this->some_field,
    ]);
}
```

### Key points to explain

- Automatically includes `workspace_id` in activity properties
- Empty logs are not submitted
- Uses sensible defaults that can be overridden via model properties
- Can temporarily disable logging with `Model::withoutActivityLogging(fn() => ...)`

---

## Option 4: Module

Modules are the core organizational unit. Each module has a Boot class that declares which lifecycle events it listens to.

### Gather information

Ask the user for:
- **Module name** (e.g., `Billing`, `Notifications`)
- **What the module provides** (web routes, admin panel, API, console commands)

### Create the directory structure

```
packages/core-php/src/Mod/{ModuleName}/
├── Boot.php                    # Module entry point
├── Models/                     # Eloquent models
├── Actions/                    # Business logic
├── Routes/
│   ├── web.php                # Web routes
│   └── api.php                # API routes
├── View/
│   └── Blade/                 # Blade views
├── Console/                   # Artisan commands
├── Database/
│   ├── Migrations/            # Database migrations
│   └── Seeders/               # Database seeders
└── Lang/
    └── en_GB/                 # Translations
```

### Generate Boot.php

```php
<?php

declare(strict_types=1);

namespace Core\Mod\{ModuleName};

use Core\Events\AdminPanelBooting;
use Core\Events\ApiRoutesRegistering;
use Core\Events\ConsoleBooting;
use Core\Events\WebRoutesRegistering;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * {ModuleName} Module Boot.
 *
 * {Description of what this module handles}
 */
class Boot extends ServiceProvider
{
    protected string $moduleName = '{module_slug}';

    /**
     * Events this module listens to for lazy loading.
     *
     * @var array<class-string, string>
     */
    public static array $listens = [
        WebRoutesRegistering::class => 'onWebRoutes',
        ApiRoutesRegistering::class => 'onApiRoutes',
        AdminPanelBooting::class => 'onAdminPanel',
        ConsoleBooting::class => 'onConsole',
    ];

    public function register(): void
    {
        // Register singletons and bindings
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadTranslationsFrom(__DIR__.'/Lang/en_GB', $this->moduleName);
    }

    // -------------------------------------------------------------------------
    // Event-driven handlers
    // -------------------------------------------------------------------------

    public function onWebRoutes(WebRoutesRegistering $event): void
    {
        $event->views($this->moduleName, __DIR__.'/View/Blade');

        if (file_exists(__DIR__.'/Routes/web.php')) {
            $event->routes(fn () => Route::middleware('web')->group(__DIR__.'/Routes/web.php'));
        }

        // Register Livewire components
        // $event->livewire('{module}.component-name', View\Components\ComponentName::class);
    }

    public function onApiRoutes(ApiRoutesRegistering $event): void
    {
        if (file_exists(__DIR__.'/Routes/api.php')) {
            $event->routes(fn () => Route::middleware('api')->group(__DIR__.'/Routes/api.php'));
        }
    }

    public function onAdminPanel(AdminPanelBooting $event): void
    {
        $event->views($this->moduleName, __DIR__.'/View/Blade');
    }

    public function onConsole(ConsoleBooting $event): void
    {
        // Register commands
        // $event->command(Console\MyCommand::class);
    }
}
```

### Available lifecycle events

| Event | Purpose | Handler receives |
|-------|---------|------------------|
| `WebRoutesRegistering` | Public web routes | views, routes, livewire |
| `AdminPanelBooting` | Admin panel setup | views, routes |
| `ApiRoutesRegistering` | REST API routes | routes |
| `ClientRoutesRegistering` | Authenticated client routes | routes |
| `ConsoleBooting` | Artisan commands | command, middleware |
| `McpToolsRegistering` | MCP tools | tools |
| `FrameworkBooted` | Late initialization | - |

### Key points to explain

- The `$listens` array declares which events trigger which methods
- Modules are lazy-loaded - only instantiated when their events fire
- Keep Boot classes thin - delegate to services and actions
- Use the `$moduleName` for consistent view namespace and translations

---

## Option 5: Seeder with Dependencies

Seeders can declare ordering via attributes for dependencies between seeders.

### Gather information

Ask the user for:
- **Seeder name** (e.g., `PackageSeeder`, `DemoDataSeeder`)
- **Module** it belongs to
- **Dependencies** - which seeders must run before this one
- **Priority** (optional) - lower numbers run first (default: 50)

### Generate the Seeder

Location: `packages/core-php/src/Mod/{Module}/Database/Seeders/{SeederName}.php`

```php
<?php

declare(strict_types=1);

namespace Core\Mod\{Module}\Database\Seeders;

use Core\Database\Seeders\Attributes\SeederAfter;
use Core\Database\Seeders\Attributes\SeederPriority;
use Core\Mod\Tenant\Database\Seeders\FeatureSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds {description}.
 */
#[SeederPriority(50)]
#[SeederAfter(FeatureSeeder::class)]
class {SeederName} extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Guard against missing tables
        if (! Schema::hasTable('your_table')) {
            return;
        }

        // Seeding logic here
    }
}
```

### Available attributes

```php
// Set priority (lower runs first, default 50)
#[SeederPriority(10)]

// Must run after these seeders
#[SeederAfter(FeatureSeeder::class)]
#[SeederAfter(FeatureSeeder::class, PackageSeeder::class)]

// Must run before these seeders
#[SeederBefore(DemoDataSeeder::class)]
```

### Priority guidelines

| Range | Use case |
|-------|----------|
| 0-20 | Foundation seeders (features, configuration) |
| 20-40 | Core data (packages, workspaces) |
| 40-60 | Default priority (general seeders) |
| 60-80 | Content seeders (pages, posts) |
| 80-100 | Demo/test data seeders |

### Key points to explain

- Always guard against missing tables with `Schema::hasTable()`
- Use `updateOrCreate()` to make seeders idempotent
- Seeders are auto-discovered from `Database/Seeders/` directories
- The framework detects circular dependencies and throws `CircularDependencyException`

---

## After generating code

Always:
1. Show the generated code with proper file paths
2. Explain what was created and why
3. Provide usage examples
4. Mention any follow-up steps (migrations, route registration, etc.)
5. Ask if they need any modifications or have questions

Remember: This is pair programming. Be helpful, explain decisions, and adapt to what the user needs.
