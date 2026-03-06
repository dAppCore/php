# Multi-Tenancy Architecture

Core PHP Framework provides robust multi-tenant isolation using workspace-scoped data. All tenant data is automatically isolated without manual filtering.

## Overview

Multi-tenancy ensures that users in one workspace (tenant) cannot access data from another workspace. Core PHP implements this through:

- Automatic query scoping via global scopes
- Workspace context validation
- Workspace-scoped caching
- Request-level workspace resolution

## Workspace Model

The `Workspace` model represents a tenant:

```php
<?php

namespace Mod\Tenant\Models;

use Illuminate\Database\Eloquent\Model;

class Workspace extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'domain',
        'is_suspended',
        'settings',
    ];

    protected $casts = [
        'is_suspended' => 'boolean',
        'settings' => 'array',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function isSuspended(): bool
    {
        return $this->is_suspended;
    }
}
```

## Making Models Workspace-Scoped

### Basic Usage

Add the `BelongsToWorkspace` trait to any model:

```php
<?php

namespace Mod\Blog\Models;

use Illuminate\Database\Eloquent\Model;
use Core\Mod\Tenant\Concerns\BelongsToWorkspace;

class Post extends Model
{
    use BelongsToWorkspace;

    protected $fillable = ['title', 'content'];
}
```

### What the Trait Provides

```php
// All queries automatically scoped to current workspace
$posts = Post::all(); // Only returns posts for current workspace

// Create automatically assigns workspace_id
$post = Post::create([
    'title' => 'Example',
    'content' => 'Content',
    // workspace_id added automatically
]);

// Cannot access posts from other workspaces
$post = Post::find(999); // null if belongs to different workspace
```

### Migration

Add `workspace_id` foreign key to tables:

```php
Schema::create('posts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
    $table->string('title');
    $table->text('content');
    $table->timestamps();

    $table->index(['workspace_id', 'created_at']);
});
```

## Workspace Scope

The `WorkspaceScope` global scope enforces data isolation:

```php
<?php

namespace Mod\Tenant\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class WorkspaceScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if ($workspace = $this->getCurrentWorkspace()) {
            $builder->where("{$model->getTable()}.workspace_id", $workspace->id);
        } elseif ($this->isStrictMode()) {
            throw new MissingWorkspaceContextException();
        }
    }

    // ...
}
```

### Strict Mode

Strict mode throws exceptions if workspace context is missing:

```php
// config/core.php
'workspace' => [
    'strict_mode' => env('WORKSPACE_STRICT_MODE', true),
],
```

**Development:** Set to `true` to catch missing context bugs early
**Production:** Keep at `true` for security

### Bypassing Workspace Scope

Sometimes you need to query across workspaces:

```php
// Query all workspaces (use with caution!)
Post::acrossWorkspaces()->get();

// Temporarily disable strict mode
WorkspaceScope::withoutStrictMode(function () {
    return Post::all();
});

// Query specific workspace
Post::forWorkspace($otherWorkspace)->get();
```

## Workspace Context

### Setting Workspace Context

The current workspace is typically set via middleware:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Mod\Tenant\Models\Workspace;

class SetWorkspaceContext
{
    public function handle(Request $request, Closure $next)
    {
        // Resolve workspace from subdomain
        $subdomain = $this->extractSubdomain($request);
        $workspace = Workspace::where('slug', $subdomain)->firstOrFail();

        // Set workspace context for this request
        app()->instance('current.workspace', $workspace);

        return $next($request);
    }
}
```

### Retrieving Current Workspace

```php
// Via helper
$workspace = workspace();

// Via container
$workspace = app('current.workspace');

// Via auth user
$workspace = auth()->user()->workspace;
```

### Middleware

Apply workspace validation middleware to routes:

```php
// Ensure workspace context exists
Route::middleware(RequireWorkspaceContext::class)->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
});
```

## Workspace-Scoped Caching

### Overview

Workspace-scoped caching ensures cache isolation between tenants:

```php
// Cache key: workspace:123:posts:recent
// Different workspace = different cache key
$posts = Post::forWorkspaceCached($workspace, 600);
```

### HasWorkspaceCache Trait

Add workspace caching to models:

```php
<?php

namespace Mod\Blog\Models;

use Illuminate\Database\Eloquent\Model;
use Core\Mod\Tenant\Concerns\BelongsToWorkspace;
use Core\Mod\Tenant\Concerns\HasWorkspaceCache;

class Post extends Model
{
    use BelongsToWorkspace, HasWorkspaceCache;
}
```

### Cache Methods

```php
// Cache for specific workspace
$posts = Post::forWorkspaceCached($workspace, 600);

// Cache for current workspace
$posts = Post::ownedByCurrentWorkspaceCached(600);

// Invalidate workspace cache
Post::invalidateWorkspaceCache($workspace);

// Invalidate all caches for a workspace
WorkspaceCacheManager::invalidateAll($workspace);
```

### Cache Configuration

```php
// config/core.php
'workspace_cache' => [
    'enabled' => env('WORKSPACE_CACHE_ENABLED', true),
    'ttl' => env('WORKSPACE_CACHE_TTL', 3600),
    'use_tags' => env('WORKSPACE_CACHE_USE_TAGS', true),
    'prefix' => 'workspace',
],
```

### Cache Tags (Recommended)

Use cache tags for granular invalidation:

```php
// Store with tags
Cache::tags(['workspace:'.$workspace->id, 'posts'])
    ->put('recent-posts', $posts, 600);

// Invalidate all posts caches for workspace
Cache::tags(['workspace:'.$workspace->id, 'posts'])->flush();

// Invalidate everything for workspace
Cache::tags(['workspace:'.$workspace->id])->flush();
```

## Database Isolation Strategies

### Shared Database (Recommended)

Single database with `workspace_id` column:

**Pros:**
- Simple deployment
- Easy backups
- Cross-workspace queries possible
- Cost-effective

**Cons:**
- Requires careful scoping
- One bad query can leak data

```php
// All tables have workspace_id
Schema::create('posts', function (Blueprint $table) {
    $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
    // ...
});
```

### Separate Databases (Advanced)

Each workspace has its own database:

**Pros:**
- Complete isolation
- Better security
- Easier compliance

**Cons:**
- Complex migrations
- Higher operational cost
- No cross-workspace queries

```php
// Dynamically switch database connection
config([
    'database.connections.workspace' => [
        'database' => "workspace_{$workspace->id}",
        // ...
    ],
]);

DB::connection('workspace')->table('posts')->get();
```

## Security Best Practices

### 1. Always Use WorkspaceScope

Never bypass workspace scoping in application code:

```php
// ✅ Good
$posts = Post::all();

// ❌ Bad - security vulnerability!
$posts = Post::withoutGlobalScope(WorkspaceScope::class)->get();
```

### 2. Validate Workspace Context

Always validate workspace exists and isn't suspended:

```php
public function handle(Request $request, Closure $next)
{
    $workspace = workspace();

    if (! $workspace) {
        throw new MissingWorkspaceContextException();
    }

    if ($workspace->isSuspended()) {
        abort(403, 'Workspace suspended');
    }

    return $next($request);
}
```

### 3. Use Policies for Authorization

Combine workspace scoping with Laravel policies:

```php
class PostPolicy
{
    public function update(User $user, Post $post): bool
    {
        // Workspace scope ensures $post belongs to current workspace
        // Policy checks user has permission within that workspace
        return $user->can('edit-posts');
    }
}
```

### 4. Audit Workspace Access

Log workspace access for security auditing:

```php
activity()
    ->causedBy($user)
    ->performedOn($workspace)
    ->withProperties(['action' => 'accessed'])
    ->log('Workspace accessed');
```

### 5. Test Cross-Workspace Isolation

Write tests to verify data isolation:

```php
public function test_cannot_access_other_workspace_data(): void
{
    $workspace1 = Workspace::factory()->create();
    $workspace2 = Workspace::factory()->create();

    $post = Post::factory()->for($workspace1)->create();

    // Set context to workspace2
    app()->instance('current.workspace', $workspace2);

    // Should not find post from workspace1
    $this->assertNull(Post::find($post->id));
}
```

## Cross-Workspace Operations

### Admin Operations

Admins sometimes need cross-workspace access:

```php
// Check if user is super admin
if (auth()->user()->isSuperAdmin()) {
    // Allow cross-workspace queries
    $allPosts = Post::acrossWorkspaces()
        ->where('published_at', '>', now()->subDays(7))
        ->get();
}
```

### Reporting

Generate reports across workspaces:

```php
class GenerateSystemReportJob
{
    public function handle(): void
    {
        $stats = WorkspaceScope::withoutStrictMode(function () {
            return [
                'total_posts' => Post::count(),
                'total_users' => User::count(),
                'by_workspace' => Workspace::withCount('posts')->get(),
            ];
        });

        // ...
    }
}
```

### Migrations

Migrations run without workspace context:

```php
public function up(): void
{
    WorkspaceScope::withoutStrictMode(function () {
        // Migrate data across all workspaces
        Post::chunk(100, function ($posts) {
            foreach ($posts as $post) {
                $post->update(['migrated' => true]);
            }
        });
    });
}
```

## Performance Optimization

### Eager Loading

Include workspace relation when needed:

```php
// ✅ Good
$posts = Post::with('workspace')->get();

// ❌ Bad - N+1 queries
$posts = Post::all();
foreach ($posts as $post) {
    echo $post->workspace->name; // N+1
}
```

### Index Optimization

Add composite indexes for workspace queries:

```php
$table->index(['workspace_id', 'created_at']);
$table->index(['workspace_id', 'status']);
$table->index(['workspace_id', 'user_id']);
```

### Partition Tables (Advanced)

For very large datasets, partition by workspace_id:

```sql
CREATE TABLE posts (
    id BIGINT,
    workspace_id BIGINT NOT NULL,
    -- ...
) PARTITION BY HASH(workspace_id) PARTITIONS 10;
```

## Monitoring

### Track Workspace Usage

Monitor workspace-level metrics:

```php
// Query count per workspace
DB::listen(function ($query) {
    $workspace = workspace();
    if ($workspace) {
        Redis::zincrby('workspace:queries', 1, $workspace->id);
    }
});

// Get top workspaces by query count
$top = Redis::zrevrange('workspace:queries', 0, 10, 'WITHSCORES');
```

### Cache Hit Rates

Track cache effectiveness per workspace:

```php
WorkspaceCacheManager::trackHit($workspace);
WorkspaceCacheManager::trackMiss($workspace);

$hitRate = WorkspaceCacheManager::getHitRate($workspace);
```

## Troubleshooting

### Missing Workspace Context

```
MissingWorkspaceContextException: Workspace context required but not set
```

**Solution:** Ensure middleware sets workspace context:

```php
Route::middleware(RequireWorkspaceContext::class)->group(/*...*/);
```

### Wrong Workspace Data

```
User sees data from different workspace
```

**Solution:** Check workspace is set correctly:

```php
dd(workspace()); // Verify correct workspace
```

### Cache Bleeding

```
Cached data appearing across workspaces
```

**Solution:** Ensure cache keys include workspace ID:

```php
// ✅ Good
$key = "workspace:{$workspace->id}:posts:recent";

// ❌ Bad
$key = "posts:recent"; // Same key for all workspaces!
```

## Learn More

- [Workspace Caching](/patterns-guide/workspace-caching)
- [Security Best Practices](/security/overview)
- [Testing Multi-Tenancy](/testing/multi-tenancy)
