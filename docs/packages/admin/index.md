# Admin Package

The Admin package provides a complete admin panel with Livewire modals, HLCRF layouts, form components, global search, and an extensible menu system.

## Installation

```bash
composer require host-uk/core-admin
```

## Quick Start

```php
<?php

namespace Mod\Blog;

use Core\Events\AdminPanelBooting;
use Core\Front\Admin\Contracts\AdminMenuProvider;
use Core\Front\Admin\Support\MenuItemBuilder;

class Boot
{
    public static array $listens = [
        AdminPanelBooting::class => 'onAdminPanel',
    ];

    public function onAdminPanel(AdminPanelBooting $event): void
    {
        // Register admin menu
        $event->menu(new BlogMenuProvider());

        // Register routes
        $event->routes(fn () => require __DIR__.'/Routes/admin.php');
    }
}
```

## Key Features

### User Interface

- **[HLCRF Layouts](/packages/admin/hlcrf)** - Composable layout system for admin interfaces
- **[Livewire Modals](/packages/admin/modals)** - Full-page modal system for forms and details
- **[Form Components](/packages/admin/forms)** - Pre-built form inputs with validation
- **[Admin Menus](/packages/admin/menus)** - Extensible navigation menu system

### Search & Discovery

- **[Global Search](/packages/admin/search)** - Unified search across all modules
- **[Search Providers](/packages/admin/search#providers)** - Register searchable resources

### Components

- **[Data Tables](/packages/admin/tables)** - Sortable, filterable data tables
- **[Cards & Grids](/packages/admin/components#cards)** - Stat cards and grid layouts
- **[Buttons & Actions](/packages/admin/components#buttons)** - Action buttons with authorization

### Features

- **[Honeypot Protection](/packages/admin/security)** - Bot detection and logging
- **[Activity Feeds](/packages/admin/activity)** - Display recent activity logs
- **[Form Validation](/packages/admin/forms#validation)** - Client and server-side validation

## Components Overview

### Form Components

```blade
<x-admin::input name="title" label="Title" required />
<x-admin::textarea name="content" label="Content" rows="10" />
<x-admin::select name="status" label="Status" :options="$statuses" />
<x-admin::checkbox name="published" label="Published" />
<x-admin::toggle name="featured" label="Featured" />
<x-admin::button type="submit">Save</x-admin::button>
```

[Learn more about Forms →](/packages/admin/forms)

### Layout Components

```blade
<x-hlcrf::layout>
    <x-hlcrf::header>
        <h1>Dashboard</h1>
    </x-hlcrf::header>

    <x-hlcrf::content>
        <x-admin::card-grid>
            <x-admin::stat-card title="Posts" :value="$postCount" />
            <x-admin::stat-card title="Users" :value="$userCount" />
        </x-admin::card-grid>
    </x-hlcrf::content>

    <x-hlcrf::right>
        <x-admin::activity-feed :limit="10" />
    </x-hlcrf::right>
</x-hlcrf::layout>
```

[Learn more about HLCRF Layouts →](/packages/admin/hlcrf)

## Admin Routes

```php
// Routes/admin.php
use Mod\Blog\View\Modal\Admin\PostEditor;
use Mod\Blog\View\Modal\Admin\PostsList;

Route::middleware(['web', 'auth', 'admin'])->prefix('admin')->group(function () {
    // Livewire modal routes
    Route::get('/posts', PostsList::class)->name('admin.blog.posts');
    Route::get('/posts/create', PostEditor::class)->name('admin.blog.posts.create');
    Route::get('/posts/{post}/edit', PostEditor::class)->name('admin.blog.posts.edit');
});
```

## Livewire Modals

Create full-page modals for admin interfaces:

```php
<?php

namespace Mod\Blog\View\Modal\Admin;

use Livewire\Component;

class PostEditor extends Component
{
    public ?Post $post = null;
    public string $title = '';
    public string $content = '';

    protected array $rules = [
        'title' => 'required|max:255',
        'content' => 'required',
    ];

    public function mount(?Post $post = null): void
    {
        $this->post = $post;
        $this->title = $post?->title ?? '';
        $this->content = $post?->content ?? '';
    }

    public function save(): void
    {
        $validated = $this->validate();

        if ($this->post) {
            $this->post->update($validated);
        } else {
            Post::create($validated);
        }

        $this->dispatch('post-saved');
        $this->redirect(route('admin.blog.posts'));
    }

    public function render()
    {
        return view('blog::admin.post-editor');
    }
}
```

[Learn more about Livewire Modals →](/packages/admin/modals)

## Global Search

Register searchable resources:

```php
<?php

namespace Mod\Blog\Search;

use Core\Admin\Search\Contracts\SearchProvider;
use Core\Admin\Search\SearchResult;

class PostSearchProvider implements SearchProvider
{
    public function search(string $query): array
    {
        return Post::where('title', 'like', "%{$query}%")
            ->limit(5)
            ->get()
            ->map(fn (Post $post) => new SearchResult(
                title: $post->title,
                description: $post->excerpt,
                url: route('admin.blog.posts.edit', $post),
                icon: 'document-text',
                category: 'Blog Posts'
            ))
            ->toArray();
    }

    public function getCategory(): string
    {
        return 'Blog';
    }
}
```

Register in your Boot.php:

```php
public function onAdminPanel(AdminPanelBooting $event): void
{
    $event->search(new PostSearchProvider());
}
```

[Learn more about Search →](/packages/admin/search)

## Configuration

```php
// config/admin.php
return [
    'middleware' => ['web', 'auth', 'admin'],
    'prefix' => 'admin',

    'menu' => [
        'auto_discover' => true,
        'cache_enabled' => true,
    ],

    'search' => [
        'enabled' => true,
        'min_length' => 2,
        'limit' => 10,
    ],

    'honeypot' => [
        'enabled' => true,
        'field_name' => env('HONEYPOT_FIELD', 'website'),
    ],
];
```

## Middleware

The admin panel uses these middleware by default:

- `web` - Web routes, sessions, CSRF
- `auth` - Require authentication
- `admin` - Check user is admin (gates/policies)

## Best Practices

### 1. Use Livewire Modals for Forms

```php
// ✅ Good - Livewire modal
Route::get('/posts/create', PostEditor::class);

// ❌ Bad - Traditional controller
Route::get('/posts/create', [PostController::class, 'create']);
```

### 2. Use Form Components

```blade
{{-- ✅ Good - consistent styling --}}
<x-admin::input name="title" label="Title" required />

{{-- ❌ Bad - custom HTML --}}
<input type="text" name="title" class="form-input">
```

### 3. Register Search Providers

```php
// ✅ Good - searchable resources
$event->search(new PostSearchProvider());
$event->search(new CategorySearchProvider());
```

### 4. Use HLCRF for Layouts

```blade
{{-- ✅ Good - composable layout --}}
<x-hlcrf::layout>
    <x-hlcrf::header>Header</x-hlcrf::header>
    <x-hlcrf::content>Content</x-hlcrf::content>
</x-hlcrf::layout>
```

## Testing

```php
<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use Mod\Tenant\Models\User;

class PostEditorTest extends TestCase
{
    public function test_admin_can_create_post(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->livewire(PostEditor::class)
            ->set('title', 'Test Post')
            ->set('content', 'Test content')
            ->call('save')
            ->assertRedirect(route('admin.blog.posts'));

        $this->assertDatabaseHas('posts', [
            'title' => 'Test Post',
        ]);
    }
}
```

## Learn More

- [HLCRF Layouts →](/packages/admin/hlcrf)
- [Livewire Modals →](/packages/admin/modals)
- [Form Components →](/packages/admin/forms)
- [Admin Menus →](/packages/admin/menus)
- [Global Search →](/packages/admin/search)
