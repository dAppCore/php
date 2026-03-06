# Multi-Tenancy

Core PHP Framework provides robust multi-tenancy with dual-level isolation: **Workspaces** for team/agency management and **Namespaces** for service isolation and billing contexts.

## Overview

The tenancy system supports three common patterns:

1. **Personal** - Individual users with personal namespaces
2. **Agency/Team** - Workspaces with multiple users managing client namespaces
3. **White-Label** - Operators creating workspace + namespace pairs for customers

## Workspaces

Workspaces represent a team, agency, or organization. Multiple users can belong to a workspace.

### Creating Workspaces

```php
use Core\Mod\Tenant\Models\Workspace;

$workspace = Workspace::create([
    'name' => 'Acme Corporation',
    'slug' => 'acme-corp',
    'tier' => 'business',
]);

// Add user to workspace
$workspace->users()->attach($user->id, [
    'role' => 'admin',
]);
```

### Workspace Scoping

Use the `BelongsToWorkspace` trait to automatically scope models:

```php
use Core\Mod\Tenant\Concerns\BelongsToWorkspace;

class Post extends Model
{
    use BelongsToWorkspace;
}

// Queries automatically scoped to current workspace
$posts = Post::all(); // Only posts in current workspace

// Create within workspace
$post = Post::create([
    'title' => 'My Post',
]); // workspace_id automatically set
```

### Workspace Context

The current workspace is resolved from:

1. Session (for web requests)
2. `X-Workspace-ID` header (for API requests)
3. Query parameter `workspace_id`
4. User's default workspace (fallback)

```php
// Get current workspace
$workspace = workspace();

// Check if workspace context is set
if (workspace()) {
    // Workspace context available
}

// Manually set workspace
Workspace::setCurrent($workspace);
```

## Namespaces

Namespaces provide service isolation and are the **billing context** for entitlements. A namespace can be owned by a **User** (personal) or a **Workspace** (agency/client).

### Why Namespaces?

- **Service Isolation** - Each namespace has separate storage, API quotas, features
- **Billing Context** - Packages and entitlements are attached to namespaces
- **Agency Pattern** - One workspace can manage many client namespaces
- **White-Label** - Operators can provision namespace + workspace pairs

### Namespace Ownership

Namespaces use polymorphic ownership:

```php
use Core\Mod\Tenant\Models\Namespace_;

// Personal namespace (owned by User)
$namespace = Namespace_::create([
    'name' => 'Personal',
    'slug' => 'personal',
    'owner_type' => User::class,
    'owner_id' => $user->id,
    'is_default' => true,
]);

// Client namespace (owned by Workspace)
$namespace = Namespace_::create([
    'name' => 'Client: Acme Corp',
    'slug' => 'client-acme',
    'owner_type' => Workspace::class,
    'owner_id' => $workspace->id,
    'workspace_id' => $workspace->id, // For billing aggregation
]);
```

### Namespace Scoping

Use the `BelongsToNamespace` trait for namespace-specific data:

```php
use Core\Mod\Tenant\Concerns\BelongsToNamespace;

class Media extends Model
{
    use BelongsToNamespace;
}

// Queries automatically scoped to current namespace
$media = Media::all();

// With caching
$media = Media::ownedByCurrentNamespaceCached(ttl: 300);
```

### Namespace Context

The current namespace is resolved from:

1. Session (for web requests)
2. `X-Namespace-ID` header (for API requests)
3. Query parameter `namespace_id`
4. User's default namespace (fallback)

```php
// Get current namespace
$namespace = namespace_context();

// Manually set namespace
Namespace_::setCurrent($namespace);
```

### Accessible Namespaces

Get all namespaces a user can access:

```php
use Core\Mod\Tenant\Services\NamespaceService;

$service = app(NamespaceService::class);

// Get all accessible namespaces
$namespaces = $service->getAccessibleNamespaces($user);

// Grouped by type
$grouped = $service->getGroupedNamespaces($user);
// Returns:
// [
//   'personal' => [...],      // User-owned namespaces
//   'workspaces' => [         // Workspace-owned namespaces
//     'Workspace Name' => [...],
//   ]
// ]
```

## Entitlements Integration

Namespaces are the billing context for entitlements:

```php
use Core\Mod\Tenant\Services\EntitlementService;

$entitlements = app(EntitlementService::class);

// Check if namespace has access to feature
$result = $entitlements->can($namespace, 'storage', quantity: 1073741824);

if ($result->isDenied()) {
    return back()->with('error', $result->getMessage());
}

// Record usage
$entitlements->recordUsage($namespace, 'api_calls', quantity: 1);

// Get current usage
$usage = $entitlements->getUsage($namespace, 'storage');
```

[Learn more about Entitlements →](/security/namespaces)

## Multi-Level Isolation

You can use both workspace and namespace scoping:

```php
class Invoice extends Model
{
    use BelongsToWorkspace, BelongsToNamespace;
}

// Query scoped to both workspace AND namespace
$invoices = Invoice::all();
```

## Workspace Caching

The framework provides workspace-isolated caching:

```php
use Core\Mod\Tenant\Concerns\HasWorkspaceCache;

class Post extends Model
{
    use BelongsToWorkspace, HasWorkspaceCache;
}

// Cache automatically isolated per workspace
$posts = Post::ownedByCurrentWorkspaceCached(ttl: 600);

// Manual workspace caching
$value = workspace_cache()->remember('stats', 600, function () {
    return $this->calculateStats();
});

// Clear workspace cache
workspace_cache()->flush();
```

### Cache Tags

When using Redis/Memcached, caches are tagged with workspace ID:

```php
// Automatically uses tag: "workspace:{id}"
workspace_cache()->put('key', 'value', 600);

// Clear all cache for workspace
workspace_cache()->flush(); // Clears all tags for current workspace
```

## Context Resolution

### Middleware

Require workspace or namespace context:

```php
use Core\Mod\Tenant\Middleware\RequireWorkspaceContext;

Route::middleware(RequireWorkspaceContext::class)->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
});
```

### Manual Resolution

```php
use Core\Mod\Tenant\Services\NamespaceService;

$service = app(NamespaceService::class);

// Resolve namespace from request
$namespace = $service->resolveFromRequest($request);

// Get default namespace for user
$namespace = $service->getDefaultNamespace($user);

// Set current namespace
$service->setCurrentNamespace($namespace);
```

## Workspace Invitations

Invite users to join workspaces:

```php
use Core\Mod\Tenant\Models\WorkspaceInvitation;

$invitation = WorkspaceInvitation::create([
    'workspace_id' => $workspace->id,
    'email' => 'user@example.com',
    'role' => 'member',
    'invited_by' => $currentUser->id,
]);

// Send invitation email
$invitation->notify(new WorkspaceInvitationNotification($invitation));

// Accept invitation
$invitation->accept($user);
```

## Usage Patterns

### Personal User (No Workspace)

```php
// User has personal namespace
$user = User::find(1);
$namespace = $user->namespaces()->where('is_default', true)->first();

// Can access services via namespace
$result = $entitlements->can($namespace, 'storage');
```

### Agency with Clients

```php
// Agency workspace owns multiple client namespaces
$workspace = Workspace::where('slug', 'agency')->first();

// Each client gets their own namespace
$clientNamespace = Namespace_::create([
    'name' => 'Client: Acme',
    'owner_type' => Workspace::class,
    'owner_id' => $workspace->id,
    'workspace_id' => $workspace->id,
]);

// Client's resources scoped to their namespace
$media = Media::where('namespace_id', $clientNamespace->id)->get();

// Workspace usage aggregated across all client namespaces
$totalUsage = $workspace->namespaces()->sum('storage_used');
```

### White-Label Operator

```php
// Operator creates workspace + namespace for customer
$workspace = Workspace::create([
    'name' => 'Customer Corp',
    'slug' => 'customer-corp',
]);

$namespace = Namespace_::create([
    'name' => 'Customer Corp Services',
    'owner_type' => Workspace::class,
    'owner_id' => $workspace->id,
    'workspace_id' => $workspace->id,
]);

// Attach package to namespace
$namespace->packages()->attach($packageId, [
    'expires_at' => now()->addYear(),
]);

// Add user to workspace
$workspace->users()->attach($userId, ['role' => 'admin']);
```

## Testing

### Setting Workspace Context

```php
use Core\Mod\Tenant\Models\Workspace;

class PostTest extends TestCase
{
    public function test_creates_post_in_workspace(): void
    {
        $workspace = Workspace::factory()->create();
        Workspace::setCurrent($workspace);

        $post = Post::create(['title' => 'Test']);

        $this->assertEquals($workspace->id, $post->workspace_id);
    }
}
```

### Setting Namespace Context

```php
use Core\Mod\Tenant\Models\Namespace_;

class MediaTest extends TestCase
{
    public function test_uploads_media_to_namespace(): void
    {
        $namespace = Namespace_::factory()->create();
        Namespace_::setCurrent($namespace);

        $media = Media::create(['filename' => 'test.jpg']);

        $this->assertEquals($namespace->id, $media->namespace_id);
    }
}
```

## Database Schema

### Workspaces Table

```sql
CREATE TABLE workspaces (
    id BIGINT PRIMARY KEY,
    uuid VARCHAR(36) UNIQUE,
    name VARCHAR(255),
    slug VARCHAR(255) UNIQUE,
    tier VARCHAR(50),
    settings JSON,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### Namespaces Table

```sql
CREATE TABLE namespaces (
    id BIGINT PRIMARY KEY,
    uuid VARCHAR(36) UNIQUE,
    name VARCHAR(255),
    slug VARCHAR(255),
    owner_type VARCHAR(255),    -- User::class or Workspace::class
    owner_id BIGINT,
    workspace_id BIGINT NULL,   -- Billing context
    settings JSON,
    is_default BOOLEAN,
    is_active BOOLEAN,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    INDEX idx_owner (owner_type, owner_id),
    INDEX idx_workspace (workspace_id)
);
```

### Workspace Users Table

```sql
CREATE TABLE workspace_user (
    id BIGINT PRIMARY KEY,
    workspace_id BIGINT,
    user_id BIGINT,
    role VARCHAR(50),
    joined_at TIMESTAMP,

    UNIQUE KEY (workspace_id, user_id)
);
```

## Best Practices

### 1. Always Use Scoping Traits

```php
// ✅ Good
class Post extends Model
{
    use BelongsToWorkspace;
}

// ❌ Bad - manual scoping
Post::where('workspace_id', workspace()->id)->get();
```

### 2. Use Namespace for Service Resources

```php
// ✅ Good - namespace scoped
class Media extends Model
{
    use BelongsToNamespace;
}

// ❌ Bad - workspace scoped for service resources
class Media extends Model
{
    use BelongsToWorkspace; // Wrong context
}
```

### 3. Cache with Workspace Isolation

```php
// ✅ Good
$stats = workspace_cache()->remember('stats', 600, fn () => $this->calculate());

// ❌ Bad - global cache conflicts
$stats = Cache::remember('stats', 600, fn () => $this->calculate());
```

### 4. Validate Entitlements Before Actions

```php
// ✅ Good
public function store(Request $request)
{
    $result = $entitlements->can(namespace_context(), 'posts', quantity: 1);

    if ($result->isDenied()) {
        return back()->with('error', $result->getMessage());
    }

    return CreatePost::run($request->validated());
}
```

## Learn More

- [Namespaces & Entitlements →](/security/namespaces)
- [Architecture: Multi-Tenancy →](/architecture/multi-tenancy)
- [Workspace Caching →](#workspace-caching)
- [Testing Multi-Tenancy →](/guide/testing#multi-tenancy)
