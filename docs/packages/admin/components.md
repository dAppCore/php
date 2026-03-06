# Admin Components

Reusable UI components for building admin panels: cards, tables, stat widgets, and more.

## Cards

### Basic Card

```blade
<x-admin::card>
    <x-slot:header>
        <h3>Recent Posts</h3>
    </x-slot:header>

    <p>Card content goes here...</p>

    <x-slot:footer>
        <a href="{{ route('posts.index') }}">View All</a>
    </x-slot:footer>
</x-admin::card>
```

### Card with Actions

```blade
<x-admin::card>
    <x-slot:header>
        <h3>Post Statistics</h3>
        <x-slot:actions>
            <x-admin::button size="sm" wire:click="refresh">
                Refresh
            </x-admin::button>
        </x-slot:actions>
    </x-slot:header>

    <div class="stats">
        {{-- Statistics content --}}
    </div>
</x-admin::card>
```

### Card Grid

Display cards in responsive grid:

```blade
<x-admin::card-grid>
    <x-admin::card>
        <h4>Total Posts</h4>
        <p class="text-3xl">1,234</p>
    </x-admin::card>

    <x-admin::card>
        <h4>Published</h4>
        <p class="text-3xl">856</p>
    </x-admin::card>

    <x-admin::card>
        <h4>Drafts</h4>
        <p class="text-3xl">378</p>
    </x-admin::card>
</x-admin::card-grid>
```

## Stat Widgets

### Simple Stat

```blade
<x-admin::stat
    label="Total Revenue"
    value="£45,231"
    icon="heroicon-o-currency-pound"
    color="green"
/>
```

### Stat with Trend

```blade
<x-admin::stat
    label="Active Users"
    :value="$activeUsers"
    icon="heroicon-o-users"
    :trend="$userTrend"
    trendLabel="vs last month"
/>
```

**Trend Indicators:**
- Positive number: green up arrow
- Negative number: red down arrow
- Zero: neutral indicator

### Stat with Chart

```blade
<x-admin::stat
    label="Page Views"
    :value="$pageViews"
    icon="heroicon-o-eye"
    :sparkline="$viewsData"
/>
```

**Sparkline Data:**

```php
public function getSparklineData()
{
    return [
        120, 145, 132, 158, 170, 165, 180, 195, 185, 200
    ];
}
```

### Stat Grid

```blade
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
    <x-admin::stat
        label="Total Posts"
        :value="$stats['total']"
        icon="heroicon-o-document-text"
    />

    <x-admin::stat
        label="Published"
        :value="$stats['published']"
        icon="heroicon-o-check-circle"
        color="green"
    />

    <x-admin::stat
        label="Drafts"
        :value="$stats['drafts']"
        icon="heroicon-o-pencil"
        color="yellow"
    />

    <x-admin::stat
        label="Archived"
        :value="$stats['archived']"
        icon="heroicon-o-archive-box"
        color="gray"
    />
</div>
```

## Tables

### Basic Table

```blade
<x-admin::table>
    <x-slot:header>
        <x-admin::table.th>Title</x-admin::table.th>
        <x-admin::table.th>Author</x-admin::table.th>
        <x-admin::table.th>Status</x-admin::table.th>
        <x-admin::table.th>Actions</x-admin::table.th>
    </x-slot:header>

    @foreach($posts as $post)
        <x-admin::table.tr>
            <x-admin::table.td>{{ $post->title }}</x-admin::table.td>
            <x-admin::table.td>{{ $post->author->name }}</x-admin::table.td>
            <x-admin::table.td>
                <x-admin::badge :color="$post->status_color">
                    {{ $post->status }}
                </x-admin::badge>
            </x-admin::table.td>
            <x-admin::table.td>
                <x-admin::button size="sm" wire:click="edit({{ $post->id }})">
                    Edit
                </x-admin::button>
            </x-admin::table.td>
        </x-admin::table.tr>
    @endforeach
</x-admin::table>
```

### Sortable Table

```blade
<x-admin::table>
    <x-slot:header>
        <x-admin::table.th sortable wire:click="sortBy('title')" :active="$sortField === 'title'">
            Title
        </x-admin::table.th>
        <x-admin::table.th sortable wire:click="sortBy('created_at')" :active="$sortField === 'created_at'">
            Created
        </x-admin::table.th>
    </x-slot:header>

    {{-- Table rows --}}
</x-admin::table>
```

**Livewire Component:**

```php
class PostsTable extends Component
{
    public $sortField = 'created_at';
    public $sortDirection = 'desc';

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function render()
    {
        $posts = Post::orderBy($this->sortField, $this->sortDirection)
            ->paginate(20);

        return view('livewire.posts-table', compact('posts'));
    }
}
```

### Table with Bulk Actions

```blade
<x-admin::table>
    <x-slot:header>
        <x-admin::table.th>
            <x-admin::checkbox wire:model.live="selectAll" />
        </x-admin::table.th>
        <x-admin::table.th>Title</x-admin::table.th>
        <x-admin::table.th>Actions</x-admin::table.th>
    </x-slot:header>

    @foreach($posts as $post)
        <x-admin::table.tr>
            <x-admin::table.td>
                <x-admin::checkbox wire:model.live="selected" value="{{ $post->id }}" />
            </x-admin::table.td>
            <x-admin::table.td>{{ $post->title }}</x-admin::table.td>
            <x-admin::table.td>...</x-admin::table.td>
        </x-admin::table.tr>
    @endforeach
</x-admin::table>

@if(count($selected) > 0)
    <div class="bulk-actions">
        <p>{{ count($selected) }} selected</p>
        <x-admin::button wire:click="bulkPublish">Publish</x-admin::button>
        <x-admin::button wire:click="bulkDelete" color="red">Delete</x-admin::button>
    </div>
@endif
```

## Badges

### Status Badges

```blade
<x-admin::badge color="green">Published</x-admin::badge>
<x-admin::badge color="yellow">Draft</x-admin::badge>
<x-admin::badge color="red">Archived</x-admin::badge>
<x-admin::badge color="blue">Scheduled</x-admin::badge>
<x-admin::badge color="gray">Pending</x-admin::badge>
```

### Badge with Dot

```blade
<x-admin::badge color="green" dot>
    Active
</x-admin::badge>
```

### Badge with Icon

```blade
<x-admin::badge color="blue">
    <x-slot:icon>
        <svg>...</svg>
    </x-slot:icon>
    Verified
</x-admin::badge>
```

### Removable Badge

```blade
<x-admin::badge
    color="blue"
    removable
    wire:click="removeTag({{ $tag->id }})"
>
    {{ $tag->name }}
</x-admin::badge>
```

## Alerts

### Basic Alert

```blade
<x-admin::alert type="success">
    Post published successfully!
</x-admin::alert>

<x-admin::alert type="error">
    Failed to save post. Please try again.
</x-admin::alert>

<x-admin::alert type="warning">
    This post has not been reviewed yet.
</x-admin::alert>

<x-admin::alert type="info">
    You have 3 draft posts.
</x-admin::alert>
```

### Dismissible Alert

```blade
<x-admin::alert type="success" dismissible>
    Post published successfully!
</x-admin::alert>
```

### Alert with Title

```blade
<x-admin::alert type="warning">
    <x-slot:title>
        Pending Review
    </x-slot:title>
    This post requires approval before it can be published.
</x-admin::alert>
```

## Empty States

### Basic Empty State

```blade
<x-admin::empty-state>
    <x-slot:icon>
        <svg>...</svg>
    </x-slot:icon>

    <x-slot:title>
        No posts yet
    </x-slot:title>

    <x-slot:description>
        Get started by creating your first blog post.
    </x-slot:description>

    <x-slot:action>
        <x-admin::button wire:click="create">
            Create Post
        </x-admin::button>
    </x-slot:action>
</x-admin::empty-state>
```

### Search Empty State

```blade
@if($posts->isEmpty() && $search)
    <x-admin::empty-state>
        <x-slot:title>
            No results found
        </x-slot:title>

        <x-slot:description>
            No posts match your search for "{{ $search }}".
        </x-slot:description>

        <x-slot:action>
            <x-admin::button wire:click="clearSearch">
                Clear Search
            </x-admin::button>
        </x-slot:action>
    </x-admin::empty-state>
@endif
```

## Loading States

### Skeleton Loaders

```blade
<x-admin::skeleton type="card" />
<x-admin::skeleton type="table" rows="5" />
<x-admin::skeleton type="text" lines="3" />
```

### Loading Spinner

```blade
<div wire:loading>
    <x-admin::spinner />
</div>

<div wire:loading.remove>
    {{-- Content --}}
</div>
```

### Loading Overlay

```blade
<div wire:loading.class="opacity-50 pointer-events-none">
    {{-- Content becomes translucent while loading --}}
</div>

<div wire:loading class="loading-overlay">
    <x-admin::spinner size="lg" />
</div>
```

## Pagination

```blade
<x-admin::table>
    {{-- Table content --}}
</x-admin::table>

{{ $posts->links('admin::pagination') }}
```

**Custom Pagination:**

```blade
<nav class="pagination">
    {{ $posts->appends(request()->query())->links() }}
</nav>
```

## Modals (See Modals Documentation)

See [Livewire Modals →](/packages/admin/modals) for full modal documentation.

## Dropdowns

### Basic Dropdown

```blade
<x-admin::dropdown>
    <x-slot:trigger>
        <x-admin::button>
            Actions
        </x-admin::button>
    </x-slot:trigger>

    <x-admin::dropdown.item wire:click="edit">
        Edit
    </x-admin::dropdown.item>

    <x-admin::dropdown.item wire:click="duplicate">
        Duplicate
    </x-admin::dropdown.item>

    <x-admin::dropdown.divider />

    <x-admin::dropdown.item wire:click="delete" color="red">
        Delete
    </x-admin::dropdown.item>
</x-admin::dropdown>
```

### Dropdown with Icons

```blade
<x-admin::dropdown>
    <x-slot:trigger>
        <button>⋮</button>
    </x-slot:trigger>

    <x-admin::dropdown.item wire:click="edit">
        <x-slot:icon>
            <svg>...</svg>
        </x-slot:icon>
        Edit Post
    </x-admin::dropdown.item>

    <x-admin::dropdown.item wire:click="view">
        <x-slot:icon>
            <svg>...</svg>
        </x-slot:icon>
        View
    </x-admin::dropdown.item>
</x-admin::dropdown>
```

## Tabs

```blade
<x-admin::tabs>
    <x-admin::tab
        name="general"
        label="General"
        :active="$activeTab === 'general'"
        wire:click="$set('activeTab', 'general')"
    >
        {{-- General settings --}}
    </x-admin::tab>

    <x-admin::tab
        name="seo"
        label="SEO"
        :active="$activeTab === 'seo'"
        wire:click="$set('activeTab', 'seo')"
    >
        {{-- SEO settings --}}
    </x-admin::tab>

    <x-admin::tab
        name="advanced"
        label="Advanced"
        :active="$activeTab === 'advanced'"
        wire:click="$set('activeTab', 'advanced')"
    >
        {{-- Advanced settings --}}
    </x-admin::tab>
</x-admin::tabs>
```

## Best Practices

### 1. Use Semantic Components

```blade
{{-- ✅ Good - semantic component --}}
<x-admin::stat
    label="Revenue"
    :value="$revenue"
    icon="heroicon-o-currency-pound"
/>

{{-- ❌ Bad - manual markup --}}
<div class="stat">
    <p>Revenue</p>
    <span>{{ $revenue }}</span>
</div>
```

### 2. Consistent Colors

```blade
{{-- ✅ Good - use color props --}}
<x-admin::badge color="green">Active</x-admin::badge>
<x-admin::badge color="red">Inactive</x-admin::badge>

{{-- ❌ Bad - custom classes --}}
<span class="bg-green-500">Active</span>
```

### 3. Loading States

```blade
{{-- ✅ Good - show loading state --}}
<div wire:loading>
    <x-admin::spinner />
</div>

{{-- ❌ Bad - no feedback --}}
<button wire:click="save">Save</button>
```

### 4. Empty States

```blade
{{-- ✅ Good - helpful empty state --}}
@if($posts->isEmpty())
    <x-admin::empty-state>
        <x-slot:action>
            <x-admin::button wire:click="create">
                Create First Post
            </x-admin::button>
        </x-slot:action>
    </x-admin::empty-state>
@endif

{{-- ❌ Bad - no guidance --}}
@if($posts->isEmpty())
    <p>No posts</p>
@endif
```

## Testing Components

```php
use Tests\TestCase;

class ComponentsTest extends TestCase
{
    public function test_stat_widget_renders(): void
    {
        $view = $this->blade('<x-admin::stat label="Users" value="100" />');

        $view->assertSee('Users');
        $view->assertSee('100');
    }

    public function test_badge_renders_with_color(): void
    {
        $view = $this->blade('<x-admin::badge color="green">Active</x-admin::badge>');

        $view->assertSee('Active');
        $view->assertSeeInOrder(['class', 'green']);
    }
}
```

## Learn More

- [Form Components →](/packages/admin/forms)
- [Livewire Modals →](/packages/admin/modals)
- [Authorization →](/packages/admin/authorization)
