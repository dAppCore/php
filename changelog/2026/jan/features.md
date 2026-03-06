# Core-PHP - January 2026

## Features Implemented

### Actions Pattern

`Core\Actions\Action` trait for single-purpose business logic classes.

```php
use Core\Actions\Action;

class CreateThing
{
    use Action;

    public function handle(User $user, array $data): Thing
    {
        // Complex business logic here
    }
}

// Usage
$thing = CreateThing::run($user, $data);
```

**Location:** `src/Core/Actions/Action.php`, `src/Core/Actions/Actionable.php`

---

### Multi-Tenant Data Isolation

**Files:**
- `MissingWorkspaceContextException` - Dedicated exception with factory methods
- `WorkspaceScope` - Strict mode enforcement, throws on missing context
- `BelongsToWorkspace` - Enhanced trait with context validation
- `RequireWorkspaceContext` middleware

**Usage:**
```php
Account::query()->forWorkspace($workspace)->get();
Account::query()->acrossWorkspaces()->get();
WorkspaceScope::withoutStrictMode(fn() => Account::all());
```

---

### Seeder Auto-Discovery

**Files:**
- `src/Core/Database/Seeders/SeederDiscovery.php` - Scans modules for seeders
- `src/Core/Database/Seeders/SeederRegistry.php` - Manual registration
- `src/Core/Database/Seeders/CoreDatabaseSeeder.php` - Base class with --exclude/--only
- `src/Core/Database/Seeders/Attributes/` - SeederPriority, SeederAfter, SeederBefore

**Usage:**
```php
class FeatureSeeder extends Seeder
{
    public int $priority = 10;
    public function run(): void { ... }
}

#[SeederAfter(FeatureSeeder::class)]
class PackageSeeder extends Seeder { ... }
```

**Config:** `core.seeders.auto_discover`, `core.seeders.paths`, `core.seeders.exclude`

---

### Team-Scoped Caching

**Files:**
- `src/Mod/Tenant/Services/WorkspaceCacheManager.php` - Cache management service
- `src/Mod/Tenant/Concerns/HasWorkspaceCache.php` - Trait for custom caching
- Enhanced `BelongsToWorkspace` trait

**Usage:**
```php
$projects = Project::ownedByCurrentWorkspaceCached(300);
$accounts = Account::forWorkspaceCached($workspace, 600);
```

**Config:** `core.workspace_cache.enabled`, `core.workspace_cache.ttl`, `core.workspace_cache.use_tags`

---

### Activity Logging

**Files:**
- `src/Core/Activity/Concerns/LogsActivity.php` - Model trait
- `src/Core/Activity/Services/ActivityLogService.php` - Query service
- `src/Core/Activity/Models/Activity.php` - Extended model
- `src/Core/Activity/View/Modal/Admin/ActivityFeed.php` - Livewire component
- `src/Core/Activity/Console/ActivityPruneCommand.php` - Cleanup command

**Usage:**
```php
use Core\Activity\Concerns\LogsActivity;

class Post extends Model
{
    use LogsActivity;
}

$activities = app(ActivityLogService::class)
    ->logBy($user)
    ->forWorkspace($workspace)
    ->recent(20);
```

**Config:** `core.activity.enabled`, `core.activity.retention_days`

**Requires:** `composer require spatie/laravel-activitylog`

---

### Bouncer Request Whitelisting

**Files:**
- `src/Core/Bouncer/Gate/Migrations/` - Database tables
- `src/Core/Bouncer/Gate/Models/ActionPermission.php` - Permission model
- `src/Core/Bouncer/Gate/Models/ActionRequest.php` - Audit log model
- `src/Core/Bouncer/Gate/ActionGateService.php` - Core service
- `src/Core/Bouncer/Gate/ActionGateMiddleware.php` - Middleware
- `src/Core/Bouncer/Gate/Attributes/Action.php` - Controller attribute
- `src/Core/Bouncer/Gate/RouteActionMacro.php` - Route macro

**Usage:**
```php
// Route-level
Route::post('/products', [ProductController::class, 'store'])
    ->action('product.create');

// Controller attribute
#[Action('product.delete', scope: 'product')]
public function destroy(Product $product) { ... }
```

**Config:** `core.bouncer.training_mode`, `core.bouncer.enabled`

---

### CDN Integration Tests

Comprehensive test suite for CDN operations and asset pipeline.

**Files:**
- `src/Core/Tests/Feature/CdnIntegrationTest.php` - Full integration test suite

**Coverage:**
- URL building (CDN, origin, private, apex)
- Asset pipeline (upload, store, delete)
- Storage operations (public/private buckets)
- vBucket isolation and path generation
- URL versioning and query parameters
- Signed URL generation
- Large file handling
- Special character handling in filenames
- Multi-file deletion
- File existence checks and metadata

**Test count:** 30+ assertions across URL generation, storage, and retrieval
