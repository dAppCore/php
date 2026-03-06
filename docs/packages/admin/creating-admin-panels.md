# Creating Admin Panels

This guide covers the complete process of creating admin panels in the Core PHP Framework, including menu registration, modal creation, and authorization integration.

## Overview

Admin panels in Core PHP use:
- **AdminMenuProvider** - Interface for menu registration
- **Livewire Modals** - Full-page components for admin interfaces
- **Authorization Props** - Built-in permission checking on components
- **HLCRF Layouts** - Composable layout system

## Menu Registration with AdminMenuProvider

### Implementing AdminMenuProvider

The `AdminMenuProvider` interface allows modules to contribute navigation items to the admin sidebar.

```php
<?php

namespace Mod\Blog;

use Core\Events\AdminPanelBooting;
use Core\Front\Admin\Concerns\HasMenuPermissions;
use Core\Front\Admin\Contracts\AdminMenuProvider;
use Core\Front\Admin\AdminMenuRegistry;
use Illuminate\Support\ServiceProvider;

class Boot extends ServiceProvider implements AdminMenuProvider
{
    use HasMenuPermissions;

    public static array $listens = [
        AdminPanelBooting::class => 'onAdminPanel',
    ];

    public function onAdminPanel(AdminPanelBooting $event): void
    {
        // Register views and routes
        $event->views('blog', __DIR__.'/View/Blade');
        $event->routes(fn () => require __DIR__.'/Routes/admin.php');

        // Register menu provider
        app(AdminMenuRegistry::class)->register($this);
    }

    public function adminMenuItems(): array
    {
        return [
            // Dashboard item in standalone group
            [
                'group' => 'dashboard',
                'priority' => self::PRIORITY_HIGH,
                'item' => fn () => [
                    'label' => 'Blog Dashboard',
                    'icon' => 'newspaper',
                    'href' => route('admin.blog.dashboard'),
                    'active' => request()->routeIs('admin.blog.dashboard'),
                ],
            ],

            // Service item with entitlement
            [
                'group' => 'services',
                'priority' => self::PRIORITY_NORMAL,
                'entitlement' => 'core.srv.blog',
                'item' => fn () => [
                    'label' => 'Blog',
                    'icon' => 'newspaper',
                    'href' => route('admin.blog.posts'),
                    'active' => request()->routeIs('admin.blog.*'),
                    'color' => 'blue',
                    'badge' => Post::draft()->count() ?: null,
                    'children' => [
                        ['label' => 'All Posts', 'href' => route('admin.blog.posts'), 'icon' => 'document-text'],
                        ['label' => 'Categories', 'href' => route('admin.blog.categories'), 'icon' => 'folder'],
                        ['label' => 'Tags', 'href' => route('admin.blog.tags'), 'icon' => 'tag'],
                    ],
                ],
            ],

            // Admin-only item
            [
                'group' => 'admin',
                'priority' => self::PRIORITY_LOW,
                'admin' => true,
                'item' => fn () => [
                    'label' => 'Blog Settings',
                    'icon' => 'gear',
                    'href' => route('admin.blog.settings'),
                    'active' => request()->routeIs('admin.blog.settings'),
                ],
            ],
        ];
    }
}
```

### Menu Item Structure

Each item in `adminMenuItems()` follows this structure:

| Property | Type | Description |
|----------|------|-------------|
| `group` | string | Menu group: `dashboard`, `workspaces`, `services`, `settings`, `admin` |
| `priority` | int | Order within group (use `PRIORITY_*` constants) |
| `entitlement` | string | Optional workspace feature code for access |
| `permissions` | array | Optional user permission keys required |
| `admin` | bool | Requires Hades/admin user |
| `item` | Closure | Lazy-evaluated item data |

### Priority Constants

```php
use Core\Front\Admin\Contracts\AdminMenuProvider;

// Available priority constants
AdminMenuProvider::PRIORITY_FIRST       // 0-9: System items
AdminMenuProvider::PRIORITY_HIGH        // 10-19: Primary navigation
AdminMenuProvider::PRIORITY_ABOVE_NORMAL // 20-39: Important items
AdminMenuProvider::PRIORITY_NORMAL      // 40-60: Standard items (default)
AdminMenuProvider::PRIORITY_BELOW_NORMAL // 61-79: Less important
AdminMenuProvider::PRIORITY_LOW         // 80-89: Rarely used
AdminMenuProvider::PRIORITY_LAST        // 90-99: End items
```

### Menu Groups

| Group | Description | Rendering |
|-------|-------------|-----------|
| `dashboard` | Primary entry points | Standalone items |
| `workspaces` | Workspace management | Grouped dropdown |
| `services` | Application services | Standalone items |
| `settings` | User/account settings | Grouped dropdown |
| `admin` | Platform administration | Grouped dropdown (Hades only) |

### Using MenuItemBuilder

For complex menus, use the fluent `MenuItemBuilder`:

```php
use Core\Front\Admin\Support\MenuItemBuilder;

public function adminMenuItems(): array
{
    return [
        MenuItemBuilder::make('Commerce')
            ->icon('shopping-cart')
            ->route('admin.commerce.dashboard')
            ->inServices()
            ->priority(self::PRIORITY_NORMAL)
            ->entitlement('core.srv.commerce')
            ->color('green')
            ->badge('New', 'green')
            ->activeOnRoute('admin.commerce.*')
            ->children([
                MenuItemBuilder::child('Products', route('admin.commerce.products'))
                    ->icon('cube'),
                MenuItemBuilder::child('Orders', route('admin.commerce.orders'))
                    ->icon('receipt')
                    ->badge(fn () => Order::pending()->count()),
                ['separator' => true],
                MenuItemBuilder::child('Settings', route('admin.commerce.settings'))
                    ->icon('gear'),
            ])
            ->build(),

        MenuItemBuilder::make('Analytics')
            ->icon('chart-line')
            ->route('admin.analytics.dashboard')
            ->inServices()
            ->entitlement('core.srv.analytics')
            ->adminOnly() // Requires admin user
            ->build(),
    ];
}
```

### Permission Checking

The `HasMenuPermissions` trait provides default permission handling:

```php
use Core\Front\Admin\Concerns\HasMenuPermissions;

class BlogMenuProvider implements AdminMenuProvider
{
    use HasMenuPermissions;

    // Override for custom global permissions
    public function menuPermissions(): array
    {
        return ['blog.view'];
    }

    // Override for custom permission logic
    public function canViewMenu(?object $user, ?object $workspace): bool
    {
        if ($user === null) {
            return false;
        }

        // Custom logic
        return $user->hasRole('editor') || $user->isHades();
    }
}
```

## Creating Livewire Modals

Livewire modals are full-page components that provide seamless admin interfaces.

### Basic Modal Structure

```php
<?php

namespace Mod\Blog\View\Modal\Admin;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Mod\Blog\Models\Post;

#[Title('Edit Post')]
#[Layout('admin::layouts.app')]
class PostEditor extends Component
{
    public ?Post $post = null;
    public string $title = '';
    public string $content = '';
    public string $status = 'draft';

    protected array $rules = [
        'title' => 'required|string|max:255',
        'content' => 'required|string',
        'status' => 'required|in:draft,published,archived',
    ];

    public function mount(?Post $post = null): void
    {
        $this->post = $post;

        if ($post) {
            $this->title = $post->title;
            $this->content = $post->content;
            $this->status = $post->status;
        }
    }

    public function save(): void
    {
        $validated = $this->validate();

        if ($this->post) {
            $this->post->update($validated);
            $message = 'Post updated successfully.';
        } else {
            Post::create($validated);
            $message = 'Post created successfully.';
        }

        session()->flash('success', $message);
        $this->redirect(route('admin.blog.posts'));
    }

    public function render(): View
    {
        return view('blog::admin.post-editor');
    }
}
```

### Modal View with HLCRF

```blade
{{-- resources/views/admin/post-editor.blade.php --}}
<x-hlcrf::layout>
    <x-hlcrf::header>
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">
                {{ $post ? 'Edit Post' : 'Create Post' }}
            </h1>

            <a href="{{ route('admin.blog.posts') }}" class="btn-ghost">
                <x-icon name="x" class="w-5 h-5" />
            </a>
        </div>
    </x-hlcrf::header>

    <x-hlcrf::content>
        <form wire:submit="save" class="space-y-6">
            <x-forms.input
                id="title"
                label="Title"
                wire:model="title"
                placeholder="Enter post title"
            />

            <x-forms.textarea
                id="content"
                label="Content"
                wire:model="content"
                rows="15"
                placeholder="Write your content here..."
            />

            <x-forms.select
                id="status"
                label="Status"
                wire:model="status"
            >
                <flux:select.option value="draft">Draft</flux:select.option>
                <flux:select.option value="published">Published</flux:select.option>
                <flux:select.option value="archived">Archived</flux:select.option>
            </x-forms.select>

            <div class="flex gap-3">
                <x-forms.button type="submit">
                    {{ $post ? 'Update' : 'Create' }} Post
                </x-forms.button>

                <x-forms.button
                    variant="secondary"
                    type="button"
                    onclick="window.location.href='{{ route('admin.blog.posts') }}'"
                >
                    Cancel
                </x-forms.button>
            </div>
        </form>
    </x-hlcrf::content>

    <x-hlcrf::right>
        <div class="p-4 bg-gray-50 rounded-lg">
            <h3 class="font-medium mb-2">Publishing Tips</h3>
            <ul class="text-sm text-gray-600 space-y-1">
                <li>Use descriptive titles</li>
                <li>Save as draft first</li>
                <li>Preview before publishing</li>
            </ul>
        </div>
    </x-hlcrf::right>
</x-hlcrf::layout>
```

### Modal with Authorization

```php
<?php

namespace Mod\Blog\View\Modal\Admin;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class PostEditor extends Component
{
    use AuthorizesRequests;

    public Post $post;

    public function mount(Post $post): void
    {
        // Authorize on mount
        $this->authorize('update', $post);

        $this->post = $post;
        // ... load data
    }

    public function save(): void
    {
        // Re-authorize on save
        $this->authorize('update', $this->post);

        $this->post->update([...]);
    }

    public function publish(): void
    {
        // Different authorization for publish
        $this->authorize('publish', $this->post);

        $this->post->update(['status' => 'published']);
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->post);

        $this->post->delete();
        $this->redirect(route('admin.blog.posts'));
    }
}
```

### Modal with File Uploads

```php
<?php

namespace Mod\Blog\View\Modal\Admin;

use Livewire\Component;
use Livewire\WithFileUploads;

class MediaUploader extends Component
{
    use WithFileUploads;

    public $image;
    public string $altText = '';

    protected array $rules = [
        'image' => 'required|image|max:5120', // 5MB max
        'altText' => 'required|string|max:255',
    ];

    public function upload(): void
    {
        $this->validate();

        $path = $this->image->store('media', 'public');

        Media::create([
            'path' => $path,
            'alt_text' => $this->altText,
            'mime_type' => $this->image->getMimeType(),
        ]);

        $this->dispatch('media-uploaded');
        $this->reset(['image', 'altText']);
    }
}
```

## Authorization Integration

### Form Component Authorization Props

All form components support authorization via `canGate` and `canResource` props:

```blade
{{-- Button disabled if user cannot update post --}}
<x-forms.button
    canGate="update"
    :canResource="$post"
>
    Save Changes
</x-forms.button>

{{-- Input disabled if user cannot update --}}
<x-forms.input
    id="title"
    wire:model="title"
    label="Title"
    canGate="update"
    :canResource="$post"
/>

{{-- Textarea with authorization --}}
<x-forms.textarea
    id="content"
    wire:model="content"
    label="Content"
    canGate="update"
    :canResource="$post"
/>

{{-- Select with authorization --}}
<x-forms.select
    id="status"
    wire:model="status"
    label="Status"
    canGate="update"
    :canResource="$post"
>
    <flux:select.option value="draft">Draft</flux:select.option>
    <flux:select.option value="published">Published</flux:select.option>
</x-forms.select>

{{-- Toggle with authorization --}}
<x-forms.toggle
    id="featured"
    wire:model="featured"
    label="Featured"
    canGate="update"
    :canResource="$post"
/>
```

### Blade Conditional Rendering

```blade
{{-- Show only if user can create --}}
@can('create', App\Models\Post::class)
    <a href="{{ route('admin.blog.posts.create') }}">New Post</a>
@endcan

{{-- Show if user can edit OR delete --}}
@canany(['update', 'delete'], $post)
    <div class="actions">
        @can('update', $post)
            <a href="{{ route('admin.blog.posts.edit', $post) }}">Edit</a>
        @endcan

        @can('delete', $post)
            <button wire:click="delete">Delete</button>
        @endcan
    </div>
@endcanany

{{-- Show message if cannot edit --}}
@cannot('update', $post)
    <p class="text-gray-500">You cannot edit this post.</p>
@endcannot
```

### Creating Policies

```php
<?php

namespace Mod\Blog\Policies;

use Core\Mod\Tenant\Models\User;
use Mod\Blog\Models\Post;

class PostPolicy
{
    /**
     * Check workspace boundary for all actions.
     */
    public function before(User $user, string $ability, mixed $model = null): ?bool
    {
        // Admins bypass all checks
        if ($user->isHades()) {
            return true;
        }

        // Enforce workspace isolation
        if ($model instanceof Post && $user->workspace_id !== $model->workspace_id) {
            return false;
        }

        return null; // Continue to specific method
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('posts.view');
    }

    public function view(User $user, Post $post): bool
    {
        return $user->hasPermission('posts.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('posts.create');
    }

    public function update(User $user, Post $post): bool
    {
        return $user->hasPermission('posts.edit')
            || $user->id === $post->author_id;
    }

    public function delete(User $user, Post $post): bool
    {
        return $user->hasRole('admin')
            || ($user->hasPermission('posts.delete') && $user->id === $post->author_id);
    }

    public function publish(User $user, Post $post): bool
    {
        return $user->hasPermission('posts.publish')
            && $post->status !== 'archived';
    }
}
```

## Complete Module Example

Here is a complete example of an admin module with menus, modals, and authorization.

### Directory Structure

```
Mod/Blog/
├── Boot.php
├── Models/
│   └── Post.php
├── Policies/
│   └── PostPolicy.php
├── View/
│   ├── Blade/
│   │   └── admin/
│   │       ├── posts-list.blade.php
│   │       └── post-editor.blade.php
│   └── Modal/
│       └── Admin/
│           ├── PostsList.php
│           └── PostEditor.php
└── Routes/
    └── admin.php
```

### Boot.php

```php
<?php

namespace Mod\Blog;

use Core\Events\AdminPanelBooting;
use Core\Front\Admin\AdminMenuRegistry;
use Core\Front\Admin\Concerns\HasMenuPermissions;
use Core\Front\Admin\Contracts\AdminMenuProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Mod\Blog\Models\Post;
use Mod\Blog\Policies\PostPolicy;

class Boot extends ServiceProvider implements AdminMenuProvider
{
    use HasMenuPermissions;

    public static array $listens = [
        AdminPanelBooting::class => 'onAdminPanel',
    ];

    public function boot(): void
    {
        // Register policy
        Gate::policy(Post::class, PostPolicy::class);
    }

    public function onAdminPanel(AdminPanelBooting $event): void
    {
        // Views
        $event->views('blog', __DIR__.'/View/Blade');

        // Routes
        $event->routes(fn () => require __DIR__.'/Routes/admin.php');

        // Menu
        app(AdminMenuRegistry::class)->register($this);

        // Livewire components
        $event->livewire('blog.admin.posts-list', View\Modal\Admin\PostsList::class);
        $event->livewire('blog.admin.post-editor', View\Modal\Admin\PostEditor::class);
    }

    public function adminMenuItems(): array
    {
        return [
            [
                'group' => 'services',
                'priority' => self::PRIORITY_NORMAL,
                'entitlement' => 'core.srv.blog',
                'permissions' => ['posts.view'],
                'item' => fn () => [
                    'label' => 'Blog',
                    'icon' => 'newspaper',
                    'href' => route('admin.blog.posts'),
                    'active' => request()->routeIs('admin.blog.*'),
                    'color' => 'blue',
                    'badge' => $this->getDraftCount(),
                    'children' => [
                        [
                            'label' => 'All Posts',
                            'href' => route('admin.blog.posts'),
                            'icon' => 'document-text',
                            'active' => request()->routeIs('admin.blog.posts'),
                        ],
                        [
                            'label' => 'Create Post',
                            'href' => route('admin.blog.posts.create'),
                            'icon' => 'plus',
                            'active' => request()->routeIs('admin.blog.posts.create'),
                        ],
                    ],
                ],
            ],
        ];
    }

    protected function getDraftCount(): ?int
    {
        $count = Post::draft()->count();
        return $count > 0 ? $count : null;
    }
}
```

### Routes/admin.php

```php
<?php

use Illuminate\Support\Facades\Route;
use Mod\Blog\View\Modal\Admin\PostEditor;
use Mod\Blog\View\Modal\Admin\PostsList;

Route::middleware(['web', 'auth', 'admin'])
    ->prefix('admin/blog')
    ->name('admin.blog.')
    ->group(function () {
        Route::get('/posts', PostsList::class)->name('posts');
        Route::get('/posts/create', PostEditor::class)->name('posts.create');
        Route::get('/posts/{post}/edit', PostEditor::class)->name('posts.edit');
    });
```

### View/Modal/Admin/PostsList.php

```php
<?php

namespace Mod\Blog\View\Modal\Admin;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Mod\Blog\Models\Post;

#[Title('Blog Posts')]
#[Layout('admin::layouts.app')]
class PostsList extends Component
{
    use WithPagination;

    public string $search = '';
    public string $status = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function posts()
    {
        return Post::query()
            ->when($this->search, fn ($q) => $q->where('title', 'like', "%{$this->search}%"))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->orderByDesc('created_at')
            ->paginate(20);
    }

    public function delete(int $postId): void
    {
        $post = Post::findOrFail($postId);

        $this->authorize('delete', $post);

        $post->delete();

        session()->flash('success', 'Post deleted.');
    }

    public function render(): View
    {
        return view('blog::admin.posts-list');
    }
}
```

### View/Blade/admin/posts-list.blade.php

```blade
<x-hlcrf::layout>
    <x-hlcrf::header>
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">Blog Posts</h1>

            @can('create', \Mod\Blog\Models\Post::class)
                <a href="{{ route('admin.blog.posts.create') }}" class="btn-primary">
                    <x-icon name="plus" class="w-4 h-4 mr-2" />
                    New Post
                </a>
            @endcan
        </div>
    </x-hlcrf::header>

    <x-hlcrf::content>
        {{-- Filters --}}
        <div class="mb-6 flex gap-4">
            <x-forms.input
                id="search"
                wire:model.live.debounce.300ms="search"
                placeholder="Search posts..."
            />

            <x-forms.select id="status" wire:model.live="status">
                <flux:select.option value="">All Statuses</flux:select.option>
                <flux:select.option value="draft">Draft</flux:select.option>
                <flux:select.option value="published">Published</flux:select.option>
            </x-forms.select>
        </div>

        {{-- Posts table --}}
        <div class="bg-white rounded-lg shadow">
            <table class="min-w-full divide-y divide-gray-200">
                <thead>
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Title</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($this->posts as $post)
                        <tr>
                            <td class="px-6 py-4">{{ $post->title }}</td>
                            <td class="px-6 py-4">
                                <span class="badge badge-{{ $post->status === 'published' ? 'green' : 'gray' }}">
                                    {{ ucfirst($post->status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4">{{ $post->created_at->format('M d, Y') }}</td>
                            <td class="px-6 py-4 text-right space-x-2">
                                @can('update', $post)
                                    <a href="{{ route('admin.blog.posts.edit', $post) }}" class="text-blue-600 hover:text-blue-800">
                                        Edit
                                    </a>
                                @endcan

                                @can('delete', $post)
                                    <button
                                        wire:click="delete({{ $post->id }})"
                                        wire:confirm="Delete this post?"
                                        class="text-red-600 hover:text-red-800"
                                    >
                                        Delete
                                    </button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center text-gray-500">
                                No posts found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="mt-4">
            {{ $this->posts->links() }}
        </div>
    </x-hlcrf::content>
</x-hlcrf::layout>
```

## Best Practices

### 1. Always Use Entitlements for Services

```php
// Menu item requires workspace entitlement
[
    'group' => 'services',
    'entitlement' => 'core.srv.blog',  // Required
    'item' => fn () => [...],
]
```

### 2. Authorize Early in Modals

```php
public function mount(Post $post): void
{
    $this->authorize('update', $post);  // Fail fast
    $this->post = $post;
}
```

### 3. Use Form Component Authorization Props

```blade
{{-- Declarative authorization --}}
<x-forms.button canGate="update" :canResource="$post">
    Save
</x-forms.button>

{{-- Not manual checks --}}
@if(auth()->user()->can('update', $post))
    <button>Save</button>
@endif
```

### 4. Keep Menu Items Lazy

```php
// Item closure is only evaluated when rendered
'item' => fn () => [
    'label' => 'Posts',
    'badge' => Post::draft()->count(),  // Computed at render time
],
```

### 5. Use HLCRF for Consistent Layouts

```blade
{{-- Always use HLCRF for admin views --}}
<x-hlcrf::layout>
    <x-hlcrf::header>...</x-hlcrf::header>
    <x-hlcrf::content>...</x-hlcrf::content>
</x-hlcrf::layout>
```

## Learn More

- [Admin Menus](/packages/admin/menus)
- [Livewire Modals](/packages/admin/modals)
- [Form Components](/packages/admin/forms)
- [Authorization](/packages/admin/authorization)
- [HLCRF Layouts](/packages/admin/hlcrf-deep-dive)
