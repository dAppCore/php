# API Scopes

Fine-grained permission control for API keys using OAuth-style scopes.

## Scope Format

Scopes follow the format: `resource:action`

**Examples:**
- `posts:read` - Read blog posts
- `posts:write` - Create and update posts
- `posts:delete` - Delete posts
- `users:*` - All user operations
- `*:read` - Read access to all resources
- `*` - Full access (use sparingly!)

## Available Scopes

### Content Management

| Scope | Description |
|-------|-------------|
| `posts:read` | View published posts |
| `posts:write` | Create and update posts |
| `posts:delete` | Delete posts |
| `posts:publish` | Publish posts |
| `pages:read` | View static pages |
| `pages:write` | Create and update pages |
| `pages:delete` | Delete pages |
| `categories:read` | View categories |
| `categories:write` | Manage categories |
| `tags:read` | View tags |
| `tags:write` | Manage tags |

### User Management

| Scope | Description |
|-------|-------------|
| `users:read` | View user profiles |
| `users:write` | Update user profiles |
| `users:delete` | Delete users |
| `users:roles` | Manage user roles |
| `users:permissions` | Manage user permissions |

### Analytics

| Scope | Description |
|-------|-------------|
| `analytics:read` | View analytics data |
| `analytics:export` | Export analytics |
| `metrics:read` | View system metrics |

### Webhooks

| Scope | Description |
|-------|-------------|
| `webhooks:read` | View webhook endpoints |
| `webhooks:write` | Create and update webhooks |
| `webhooks:delete` | Delete webhooks |
| `webhooks:manage` | Full webhook management |

### API Keys

| Scope | Description |
|-------|-------------|
| `keys:read` | View API keys |
| `keys:write` | Create API keys |
| `keys:delete` | Delete API keys |
| `keys:manage` | Full key management |

### Workspace Management

| Scope | Description |
|-------|-------------|
| `workspace:read` | View workspace details |
| `workspace:write` | Update workspace settings |
| `workspace:members` | Manage workspace members |
| `workspace:billing` | Access billing information |

### Admin Operations

| Scope | Description |
|-------|-------------|
| `admin:users` | Admin user management |
| `admin:workspaces` | Admin workspace management |
| `admin:system` | System administration |
| `admin:*` | Full admin access |

## Assigning Scopes

### API Key Creation

```php
use Mod\Api\Models\ApiKey;

$apiKey = ApiKey::create([
    'name' => 'Mobile App',
    'workspace_id' => $workspace->id,
    'scopes' => [
        'posts:read',
        'posts:write',
        'categories:read',
    ],
]);
```

### Sanctum Tokens

```php
$user = User::find(1);

$token = $user->createToken('mobile-app', [
    'posts:read',
    'posts:write',
    'analytics:read',
])->plainTextToken;
```

## Scope Enforcement

### Route Protection

```php
use Mod\Api\Middleware\EnforceApiScope;

// Single scope
Route::middleware(['auth:sanctum', 'scope:posts:write'])
    ->post('/posts', [PostController::class, 'store']);

// Multiple scopes (all required)
Route::middleware(['auth:sanctum', 'scopes:posts:write,categories:read'])
    ->post('/posts', [PostController::class, 'store']);

// Any scope (at least one required)
Route::middleware(['auth:sanctum', 'scope-any:posts:write,pages:write'])
    ->post('/content', [ContentController::class, 'store']);
```

### Controller Checks

```php
<?php

namespace Mod\Blog\Controllers\Api;

class PostController
{
    public function store(Request $request)
    {
        // Check single scope
        if (!$request->user()->tokenCan('posts:write')) {
            abort(403, 'Insufficient permissions');
        }

        // Check multiple scopes
        if (!$request->user()->tokenCan('posts:write') ||
            !$request->user()->tokenCan('categories:read')) {
            abort(403);
        }

        // Proceed with creation
        $post = Post::create($request->validated());

        return new PostResource($post);
    }

    public function publish(Post $post)
    {
        // Require specific scope for sensitive action
        if (!request()->user()->tokenCan('posts:publish')) {
            abort(403, 'Publishing requires posts:publish scope');
        }

        $post->publish();

        return new PostResource($post);
    }
}
```

## Wildcard Scopes

### Resource Wildcards

Grant all permissions for a resource:

```php
$apiKey->scopes = [
    'posts:*',      // All post operations
    'categories:*', // All category operations
];
```

**Equivalent to:**

```php
$apiKey->scopes = [
    'posts:read',
    'posts:write',
    'posts:delete',
    'posts:publish',
    'categories:read',
    'categories:write',
    'categories:delete',
];
```

### Action Wildcards

Grant read-only access to everything:

```php
$apiKey->scopes = [
    '*:read', // Read access to all resources
];
```

### Full Access

```php
$apiKey->scopes = ['*']; // Full access (dangerous!)
```

::: warning
Only use `*` scope for admin integrations. Always prefer specific scopes.
:::

## Scope Validation

### Custom Scopes

Define custom scopes for your modules:

```php
<?php

namespace Mod\Shop\Api;

use Mod\Api\Contracts\ScopeProvider;

class ShopScopeProvider implements ScopeProvider
{
    public function scopes(): array
    {
        return [
            'products:read' => 'View products',
            'products:write' => 'Create and update products',
            'products:delete' => 'Delete products',
            'orders:read' => 'View orders',
            'orders:write' => 'Process orders',
            'orders:refund' => 'Issue refunds',
        ];
    }
}
```

**Register Provider:**

```php
use Core\Events\ApiRoutesRegistering;
use Mod\Shop\Api\ShopScopeProvider;

public function onApiRoutes(ApiRoutesRegistering $event): void
{
    $event->scopes(new ShopScopeProvider());
}
```

### Scope Groups

Group related scopes:

```php
// config/api.php
return [
    'scope_groups' => [
        'content_admin' => [
            'posts:*',
            'pages:*',
            'categories:*',
            'tags:*',
        ],
        'analytics_viewer' => [
            'analytics:read',
            'metrics:read',
        ],
        'webhook_manager' => [
            'webhooks:*',
        ],
    ],
];
```

**Usage:**

```php
// Assign group instead of individual scopes
$apiKey->scopes = config('api.scope_groups.content_admin');
```

## Checking Scopes

### Token Abilities

```php
// Check if token has scope
if ($request->user()->tokenCan('posts:write')) {
    // Has permission
}

// Check multiple scopes (all required)
if ($request->user()->tokenCan('posts:write') &&
    $request->user()->tokenCan('posts:publish')) {
    // Has both permissions
}

// Get all token abilities
$abilities = $request->user()->currentAccessToken()->abilities;
```

### Scope Middleware

```php
// Require single scope
Route::middleware('scope:posts:write')->post('/posts', ...);

// Require all scopes
Route::middleware('scopes:posts:write,categories:read')->post('/posts', ...);

// Require any scope (OR logic)
Route::middleware('scope-any:posts:write,pages:write')->post('/content', ...);
```

### API Key Scopes

```php
use Mod\Api\Models\ApiKey;

$apiKey = ApiKey::findByKey($providedKey);

// Check scope
if ($apiKey->hasScope('posts:write')) {
    // Has permission
}

// Check multiple scopes
if ($apiKey->hasAllScopes(['posts:write', 'categories:read'])) {
    // Has all permissions
}

// Check any scope
if ($apiKey->hasAnyScope(['posts:write', 'pages:write'])) {
    // Has at least one permission
}
```

## Scope Inheritance

### Hierarchical Scopes

Higher-level scopes include lower-level scopes:

```
admin:* includes:
  ├─ admin:users
  ├─ admin:workspaces
  └─ admin:system

workspace:* includes:
  ├─ workspace:read
  ├─ workspace:write
  ├─ workspace:members
  └─ workspace:billing
```

**Implementation:**

```php
public function hasScope(string $scope): bool
{
    // Exact match
    if (in_array($scope, $this->scopes)) {
        return true;
    }

    // Check wildcards
    [$resource, $action] = explode(':', $scope);

    // Resource wildcard (e.g., posts:*)
    if (in_array("{$resource}:*", $this->scopes)) {
        return true;
    }

    // Action wildcard (e.g., *:read)
    if (in_array("*:{$action}", $this->scopes)) {
        return true;
    }

    // Full wildcard
    return in_array('*', $this->scopes);
}
```

## Error Responses

### Insufficient Scope

```json
{
  "message": "Insufficient scope",
  "required_scope": "posts:write",
  "provided_scopes": ["posts:read"],
  "error_code": "insufficient_scope"
}
```

**HTTP Status:** 403 Forbidden

### Missing Scope

```json
{
  "message": "This action requires the 'posts:publish' scope",
  "required_scope": "posts:publish",
  "error_code": "scope_required"
}
```

## Best Practices

### 1. Principle of Least Privilege

```php
// ✅ Good - minimal scopes
$apiKey->scopes = [
    'posts:read',
    'categories:read',
];

// ❌ Bad - excessive permissions
$apiKey->scopes = ['*'];
```

### 2. Use Specific Scopes

```php
// ✅ Good - specific actions
$apiKey->scopes = [
    'posts:read',
    'posts:write',
];

// ❌ Bad - overly broad
$apiKey->scopes = ['posts:*'];
```

### 3. Document Required Scopes

```php
/**
 * Publish a blog post.
 *
 * Required scopes:
 * - posts:write (to modify post)
 * - posts:publish (to change status)
 *
 * @requires posts:write
 * @requires posts:publish
 */
public function publish(Post $post)
{
    // ...
}
```

### 4. Validate Early

```php
// ✅ Good - check at route level
Route::middleware('scope:posts:write')
    ->post('/posts', [PostController::class, 'store']);

// ❌ Bad - check late in controller
public function store(Request $request)
{
    $validated = $request->validate([...]); // Wasted work

    if (!$request->user()->tokenCan('posts:write')) {
        abort(403);
    }
}
```

## Testing Scopes

```php
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class ScopeTest extends TestCase
{
    public function test_requires_write_scope(): void
    {
        $user = User::factory()->create();

        // Token without write scope
        Sanctum::actingAs($user, ['posts:read']);

        $response = $this->postJson('/api/v1/posts', [
            'title' => 'Test Post',
        ]);

        $response->assertStatus(403);
    }

    public function test_allows_with_correct_scope(): void
    {
        $user = User::factory()->create();

        // Token with write scope
        Sanctum::actingAs($user, ['posts:write']);

        $response = $this->postJson('/api/v1/posts', [
            'title' => 'Test Post',
            'content' => 'Content',
        ]);

        $response->assertStatus(201);
    }

    public function test_wildcard_scope_grants_access(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, ['posts:*']);

        $this->postJson('/api/v1/posts', [...])->assertStatus(201);
        $this->putJson('/api/v1/posts/1', [...])->assertStatus(200);
        $this->deleteJson('/api/v1/posts/1')->assertStatus(204);
    }
}
```

## Learn More

- [Authentication →](/packages/api/authentication)
- [Rate Limiting →](/packages/api/rate-limiting)
- [API Reference →](/api/authentication)
