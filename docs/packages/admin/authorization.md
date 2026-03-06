# Authorization

Integration with Laravel's Gate and Policy system for fine-grained authorization in admin panels.

## Form Component Authorization

All form components support authorization props:

```blade
<x-admin::button
    :can="'publish'"
    :cannot="'delete'"
    :canAny="['edit', 'update']"
>
    Publish Post
</x-admin::button>
```

### Authorization Props

**`can` - Single ability:**

```blade
<x-admin::button :can="'delete'" :model="$post">
    Delete
</x-admin::button>

{{-- Only shown if user can delete the post --}}
```

**`cannot` - Inverse check:**

```blade
<x-admin::input
    name="status"
    :cannot="'publish'"
    :model="$post"
/>

{{-- Disabled if user cannot publish --}}
```

**`canAny` - Multiple abilities (OR):**

```blade
<x-admin::button :canAny="['edit', 'update']" :model="$post">
    Edit Post
</x-admin::button>

{{-- Shown if user can either edit OR update --}}
```

## Policy Integration

### Defining Policies

```php
<?php

namespace Mod\Blog\Policies;

use Mod\Tenant\Models\User;
use Mod\Blog\Models\Post;

class PostPolicy
{
    public function view(User $user, Post $post): bool
    {
        return $user->workspace_id === $post->workspace_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('posts.create');
    }

    public function update(User $user, Post $post): bool
    {
        return $user->id === $post->author_id
            || $user->hasRole('editor');
    }

    public function delete(User $user, Post $post): bool
    {
        return $user->hasRole('admin')
            && $user->workspace_id === $post->workspace_id;
    }

    public function publish(User $user, Post $post): bool
    {
        return $user->hasPermission('posts.publish')
            && $post->status !== 'archived';
    }
}
```

### Registering Policies

```php
use Illuminate\Support\Facades\Gate;
use Mod\Blog\Models\Post;
use Mod\Blog\Policies\PostPolicy;

// In AuthServiceProvider or module Boot class
Gate::policy(Post::class, PostPolicy::class);
```

## Action Gate

Use the Action Gate system for route-level authorization:

### Defining Actions

```php
<?php

namespace Mod\Blog\Controllers;

use Core\Bouncer\Gate\Attributes\Action;

class PostController
{
    #[Action(
        name: 'posts.create',
        description: 'Create new blog posts',
        group: 'Content Management'
    )]
    public function store(Request $request)
    {
        // Only accessible to users with 'posts.create' permission
    }

    #[Action(
        name: 'posts.publish',
        description: 'Publish blog posts',
        group: 'Content Management',
        dangerous: true
    )]
    public function publish(Post $post)
    {
        // Marked as dangerous action
    }
}
```

### Route Protection

```php
use Core\Bouncer\Gate\ActionGateMiddleware;

// Protect single route
Route::post('/posts', [PostController::class, 'store'])
    ->middleware(['auth', ActionGateMiddleware::class]);

// Protect route group
Route::middleware(['auth', ActionGateMiddleware::class])
    ->group(function () {
        Route::post('/posts', [PostController::class, 'store']);
        Route::post('/posts/{post}/publish', [PostController::class, 'publish']);
    });
```

### Checking Permissions

```php
use Core\Bouncer\Gate\ActionGateService;

$gate = app(ActionGateService::class);

// Check if user can perform action
if ($gate->allows('posts.create', auth()->user())) {
    // User has permission
}

// Check with additional context
if ($gate->allows('posts.publish', auth()->user(), $post)) {
    // User can publish this specific post
}

// Get all user permissions
$permissions = $gate->getUserPermissions(auth()->user());
```

## Admin Menu Authorization

Restrict menu items by permission:

```php
use Core\Front\Admin\Support\MenuItemBuilder;

MenuItemBuilder::create('Posts')
    ->route('admin.posts.index')
    ->icon('heroicon-o-document-text')
    ->can('posts.view') // Only shown if user can view posts
    ->badge(fn () => Post::pending()->count())
    ->children([
        MenuItemBuilder::create('All Posts')
            ->route('admin.posts.index'),

        MenuItemBuilder::create('Create Post')
            ->route('admin.posts.create')
            ->can('posts.create'), // Nested permission check

        MenuItemBuilder::create('Categories')
            ->route('admin.categories.index')
            ->canAny(['categories.view', 'categories.edit']),
    ]);
```

## Livewire Modal Authorization

Protect Livewire modals with authorization checks:

```php
<?php

namespace Mod\Blog\View\Modal\Admin;

use Livewire\Component;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class PostEditor extends Component
{
    use AuthorizesRequests;

    public Post $post;

    public function mount(Post $post)
    {
        // Authorize on mount
        $this->authorize('update', $post);

        $this->post = $post;
    }

    public function save()
    {
        // Authorize action
        $this->authorize('update', $this->post);

        $this->post->save();

        $this->dispatch('post-updated');
    }

    public function publish()
    {
        // Custom authorization
        $this->authorize('publish', $this->post);

        $this->post->update(['status' => 'published']);
    }
}
```

## Workspace Scoping

Automatic workspace isolation with policies:

```php
class PostPolicy
{
    public function viewAny(User $user): bool
    {
        // User can view posts in their workspace
        return true;
    }

    public function view(User $user, Post $post): bool
    {
        // Enforce workspace boundary
        return $user->workspace_id === $post->workspace_id;
    }

    public function update(User $user, Post $post): bool
    {
        // Workspace check + additional authorization
        return $user->workspace_id === $post->workspace_id
            && ($user->id === $post->author_id || $user->hasRole('editor'));
    }
}
```

## Role-Based Authorization

### Defining Roles

```php
use Mod\Tenant\Models\User;

// Assign role
$user->assignRole('editor');

// Check role
if ($user->hasRole('admin')) {
    // User is admin
}

// Check any role
if ($user->hasAnyRole(['editor', 'author'])) {
    // User has at least one role
}

// Check all roles
if ($user->hasAllRoles(['editor', 'reviewer'])) {
    // User has both roles
}
```

### Policy with Roles

```php
class PostPolicy
{
    public function update(User $user, Post $post): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('editor') && $user->workspace_id === $post->workspace_id)
            || ($user->hasRole('author') && $user->id === $post->author_id);
    }

    public function delete(User $user, Post $post): bool
    {
        // Only admins can delete
        return $user->hasRole('admin');
    }
}
```

## Permission-Based Authorization

### Defining Permissions

```php
// Grant permission
$user->givePermission('posts.create');
$user->givePermission('posts.publish');

// Check permission
if ($user->hasPermission('posts.publish')) {
    // User can publish
}

// Check multiple permissions
if ($user->hasAllPermissions(['posts.create', 'posts.publish'])) {
    // User has all permissions
}

// Check any permission
if ($user->hasAnyPermission(['posts.edit', 'posts.delete'])) {
    // User has at least one permission
}
```

### Policy with Permissions

```php
class PostPolicy
{
    public function create(User $user): bool
    {
        return $user->hasPermission('posts.create');
    }

    public function publish(User $user, Post $post): bool
    {
        return $user->hasPermission('posts.publish')
            && $post->status === 'draft';
    }
}
```

## Conditional Rendering

### Blade Directives

```blade
@can('create', App\Models\Post::class)
    <a href="{{ route('posts.create') }}">Create Post</a>
@endcan

@cannot('delete', $post)
    <p>You cannot delete this post</p>
@endcannot

@canany(['edit', 'update'], $post)
    <a href="{{ route('posts.edit', $post) }}">Edit</a>
@endcanany
```

### Component Visibility

```blade
<x-admin::button
    :can="'publish'"
    :model="$post"
    wire:click="publish"
>
    Publish
</x-admin::button>

{{-- Automatically hidden if user cannot publish --}}
```

### Form Field Disabling

```blade
<x-admin::input
    name="slug"
    :cannot="'edit-slug'"
    :model="$post"
/>

{{-- Disabled if user cannot edit slug --}}
```

## Authorization Middleware

### Global Middleware

```php
// app/Http/Kernel.php
protected $middlewareGroups = [
    'web' => [
        // ...
        \Core\Bouncer\Gate\ActionGateMiddleware::class,
    ],
];
```

### Route Middleware

```php
// Require authentication
Route::middleware(['auth'])->group(function () {
    Route::get('/admin', [AdminController::class, 'index']);
});

// Require specific ability
Route::middleware(['can:create,App\Models\Post'])->group(function () {
    Route::get('/posts/create', [PostController::class, 'create']);
});
```

## Testing Authorization

```php
use Tests\TestCase;
use Mod\Blog\Models\Post;
use Mod\Tenant\Models\User;

class AuthorizationTest extends TestCase
{
    public function test_user_can_view_own_posts(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['author_id' => $user->id]);

        $this->assertTrue($user->can('view', $post));
    }

    public function test_user_cannot_delete_others_posts(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(); // Different author

        $this->assertFalse($user->can('delete', $post));
    }

    public function test_admin_can_delete_any_post(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $post = Post::factory()->create();

        $this->assertTrue($admin->can('delete', $post));
    }

    public function test_workspace_isolation(): void
    {
        $user1 = User::factory()->create(['workspace_id' => 1]);
        $user2 = User::factory()->create(['workspace_id' => 2]);

        $post = Post::factory()->create(['workspace_id' => 1]);

        $this->assertTrue($user1->can('view', $post));
        $this->assertFalse($user2->can('view', $post));
    }
}
```

## Best Practices

### 1. Always Check Workspace Boundaries

```php
// ✅ Good - workspace check
public function view(User $user, Post $post): bool
{
    return $user->workspace_id === $post->workspace_id;
}

// ❌ Bad - no workspace check
public function view(User $user, Post $post): bool
{
    return true; // Data leak!
}
```

### 2. Use Policies Over Gates

```php
// ✅ Good - policy
$this->authorize('update', $post);

// ❌ Bad - manual check
if (auth()->id() !== $post->author_id) {
    abort(403);
}
```

### 3. Authorize Early

```php
// ✅ Good - authorize in mount
public function mount(Post $post)
{
    $this->authorize('update', $post);
    $this->post = $post;
}

// ❌ Bad - authorize in action
public function save()
{
    $this->authorize('update', $this->post); // Too late!
    $this->post->save();
}
```

### 4. Use Authorization Props

```blade
{{-- ✅ Good - declarative authorization --}}
<x-admin::button :can="'delete'" :model="$post">
    Delete
</x-admin::button>

{{-- ❌ Bad - manual check --}}
@if(auth()->user()->can('delete', $post))
    <x-admin::button>Delete</x-admin::button>
@endif
```

## Learn More

- [Form Components →](/packages/admin/forms)
- [Admin Menus →](/packages/admin/menus)
- [Multi-Tenancy →](/packages/core/tenancy)
