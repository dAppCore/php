# Livewire Modals

The Admin package uses Livewire components as full-page modals, providing a seamless admin interface without traditional page reloads.

## Overview

Livewire modals in Core PHP:
- Render as full-page routes
- Support direct URL access
- Maintain browser history
- Work with back/forward buttons
- No JavaScript modal libraries needed

## Creating a Modal

### Basic Modal

```php
<?php

namespace Mod\Blog\View\Modal\Admin;

use Livewire\Component;
use Mod\Blog\Models\Post;

class PostEditor extends Component
{
    public ?Post $post = null;
    public string $title = '';
    public string $content = '';
    public string $status = 'draft';

    protected array $rules = [
        'title' => 'required|max:255',
        'content' => 'required',
        'status' => 'required|in:draft,published',
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
            $message = 'Post updated successfully';
        } else {
            Post::create($validated);
            $message = 'Post created successfully';
        }

        session()->flash('success', $message);
        $this->redirect(route('admin.blog.posts'));
    }

    public function render()
    {
        return view('blog::admin.post-editor')
            ->layout('admin::layouts.modal');
    }
}
```

### Modal View

```blade
{{-- resources/views/admin/post-editor.blade.php --}}
<x-hlcrf::layout>
    <x-hlcrf::header>
        <div class="flex items-center justify-between">
            <h1>{{ $post ? 'Edit Post' : 'Create Post' }}</h1>

            <button wire:click="$redirect('{{ route('admin.blog.posts') }}')" class="btn-ghost">
                <x-icon name="x" />
            </button>
        </div>
    </x-hlcrf::header>

    <x-hlcrf::content>
        <form wire:submit="save" class="space-y-6">
            <x-admin::form-group label="Title" name="title" required>
                <x-admin::input
                    name="title"
                    wire:model="title"
                    placeholder="Enter post title"
                />
            </x-admin::form-group>

            <x-admin::form-group label="Content" name="content" required>
                <x-admin::textarea
                    name="content"
                    wire:model.defer="content"
                    rows="15"
                />
            </x-admin::form-group>

            <x-admin::form-group label="Status" name="status" required>
                <x-admin::select
                    name="status"
                    :options="['draft' => 'Draft', 'published' => 'Published']"
                    wire:model="status"
                />
            </x-admin::form-group>

            <div class="flex gap-3">
                <x-admin::button type="submit" :loading="$isSaving">
                    {{ $post ? 'Update' : 'Create' }} Post
                </x-admin::button>

                <x-admin::button
                    variant="secondary"
                    wire:click="$redirect('{{ route('admin.blog.posts') }}')"
                >
                    Cancel
                </x-admin::button>
            </div>
        </form>
    </x-hlcrf::content>

    <x-hlcrf::right>
        <x-admin::help-panel>
            <h3>Publishing Tips</h3>
            <ul>
                <li>Write a clear, descriptive title</li>
                <li>Use proper formatting in content</li>
                <li>Save as draft to preview first</li>
            </ul>
        </x-admin::help-panel>
    </x-hlcrf::right>
</x-hlcrf::layout>
```

## Registering Modal Routes

```php
// Routes/admin.php
use Mod\Blog\View\Modal\Admin\PostEditor;
use Mod\Blog\View\Modal\Admin\PostsList;

Route::middleware(['web', 'auth', 'admin'])->prefix('admin/blog')->group(function () {
    Route::get('/posts', PostsList::class)->name('admin.blog.posts');
    Route::get('/posts/create', PostEditor::class)->name('admin.blog.posts.create');
    Route::get('/posts/{post}/edit', PostEditor::class)->name('admin.blog.posts.edit');
});
```

## Opening Modals

### Via Link

```blade
<a href="{{ route('admin.blog.posts.create') }}" class="btn-primary">
    New Post
</a>
```

### Via Livewire Navigate

```blade
<button wire:navigate href="{{ route('admin.blog.posts.create') }}" class="btn-primary">
    New Post
</button>
```

### Via JavaScript

```blade
<button @click="window.location.href = '{{ route('admin.blog.posts.create') }}'">
    New Post
</button>
```

## Modal Layouts

### With HLCRF

```blade
<x-hlcrf::layout>
    <x-hlcrf::header>
        Modal Header
    </x-hlcrf::header>

    <x-hlcrf::content>
        Modal Content
    </x-hlcrf::content>

    <x-hlcrf::footer>
        Modal Footer
    </x-hlcrf::footer>
</x-hlcrf::layout>
```

### Full-Width Modal

```blade
<x-hlcrf::layout variant="full-width">
    <x-hlcrf::content>
        Full-width content
    </x-hlcrf::content>
</x-hlcrf::layout>
```

### With Sidebar

```blade
<x-hlcrf::layout variant="two-column">
    <x-hlcrf::content>
        Main content
    </x-hlcrf::content>

    <x-hlcrf::right width="300px">
        Sidebar
    </x-hlcrf::right>
</x-hlcrf::layout>
```

## Advanced Patterns

### Modal with Confirmation

```php
public bool $showDeleteConfirmation = false;

public function confirmDelete(): void
{
    $this->showDeleteConfirmation = true;
}

public function delete(): void
{
    $this->post->delete();

    session()->flash('success', 'Post deleted');
    $this->redirect(route('admin.blog.posts'));
}

public function cancelDelete(): void
{
    $this->showDeleteConfirmation = false;
}
```

```blade
@if($showDeleteConfirmation)
    <div class="fixed inset-0 bg-black/50 flex items-center justify-center">
        <div class="bg-white p-6 rounded-lg max-w-md">
            <h3 class="text-lg font-semibold mb-4">Delete Post?</h3>
            <p class="mb-6">This action cannot be undone.</p>

            <div class="flex gap-3">
                <x-admin::button variant="danger" wire:click="delete">
                    Delete
                </x-admin::button>
                <x-admin::button variant="secondary" wire:click="cancelDelete">
                    Cancel
                </x-admin::button>
            </div>
        </div>
    </div>
@endif
```

### Modal with Steps

```php
public int $step = 1;

public function nextStep(): void
{
    $this->validateOnly('step' . $this->step);
    $this->step++;
}

public function previousStep(): void
{
    $this->step--;
}
```

```blade
<div>
    @if($step === 1)
        {{-- Step 1: Basic Info --}}
        <x-admin::input name="title" wire:model="title" label="Title" />
        <x-admin::button wire:click="nextStep">Next</x-admin::button>
    @elseif($step === 2)
        {{-- Step 2: Content --}}
        <x-admin::textarea name="content" wire:model="content" label="Content" />
        <x-admin::button wire:click="previousStep">Back</x-admin::button>
        <x-admin::button wire:click="nextStep">Next</x-admin::button>
    @else
        {{-- Step 3: Review --}}
        <div>Review and save...</div>
        <x-admin::button wire:click="previousStep">Back</x-admin::button>
        <x-admin::button wire:click="save">Save</x-admin::button>
    @endif
</div>
```

### Modal with Live Search

```php
public string $search = '';
public array $results = [];

public function updatedSearch(): void
{
    $this->results = Post::where('title', 'like', "%{$this->search}%")
        ->limit(10)
        ->get()
        ->toArray();
}
```

```blade
<x-admin::input
    name="search"
    wire:model.live.debounce.300ms="search"
    placeholder="Search posts..."
/>

<div class="mt-4">
    @foreach($results as $result)
        <div class="p-3 hover:bg-gray-50 cursor-pointer" wire:click="selectPost({{ $result['id'] }})">
            {{ $result['title'] }}
        </div>
    @endforeach
</div>
```

## File Uploads

### Single File

```php
use Livewire\WithFileUploads;

class PostEditor extends Component
{
    use WithFileUploads;

    public $image;

    public function save(): void
    {
        $this->validate([
            'image' => 'required|image|max:2048',
        ]);

        $path = $this->image->store('posts', 'public');

        Post::create([
            'image_path' => $path,
        ]);
    }
}
```

```blade
<x-admin::form-group label="Featured Image" name="image">
    <input type="file" wire:model="image" accept="image/*">

    @if($image)
        <img src="{{ $image->temporaryUrl() }}" class="mt-2 max-w-xs">
    @endif
</x-admin::form-group>
```

### Multiple Files

```php
public array $images = [];

public function save(): void
{
    $this->validate([
        'images.*' => 'image|max:2048',
    ]);

    foreach ($this->images as $image) {
        $path = $image->store('posts', 'public');
        // Save path...
    }
}
```

## Real-Time Validation

```php
protected array $rules = [
    'title' => 'required|max:255',
    'slug' => 'required|unique:posts,slug',
];

public function updated($propertyName): void
{
    $this->validateOnly($propertyName);
}
```

```blade
<x-admin::input
    name="slug"
    wire:model.live="slug"
    label="Slug"
/>

@error('slug')
    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
@enderror
```

## Loading States

```blade
{{-- Show loading on specific action --}}
<x-admin::button wire:click="save" wire:loading.attr="disabled">
    <span wire:loading.remove wire:target="save">Save</span>
    <span wire:loading wire:target="save">Saving...</span>
</x-admin::button>

{{-- Disable form during loading --}}
<form wire:submit="save">
    <div wire:loading.class="opacity-50 pointer-events-none">
        {{-- Form fields --}}
    </div>
</form>

{{-- Spinner --}}
<div wire:loading wire:target="save" class="spinner"></div>
```

## Events

### Dispatch Events

```php
// From modal
public function save(): void
{
    // Save logic...

    $this->dispatch('post-saved', postId: $post->id);
}
```

### Listen to Events

```php
// In another component
protected $listeners = ['post-saved' => 'refreshPosts'];

public function refreshPosts(int $postId): void
{
    $this->posts = Post::all();
}
```

```blade
{{-- In Blade --}}
<div
    x-data
    @post-saved.window="alert('Post saved!')"
>
</div>
```

## Best Practices

### 1. Use Route Model Binding

```php
// ✅ Good - automatic model resolution
Route::get('/posts/{post}/edit', PostEditor::class);

public function mount(?Post $post = null): void
{
    $this->post = $post;
}
```

### 2. Flash Messages

```php
// ✅ Good - inform user of success
public function save(): void
{
    // Save logic...

    session()->flash('success', 'Post saved');
    $this->redirect(route('admin.blog.posts'));
}
```

### 3. Validate Early

```php
// ✅ Good - real-time validation
public function updated($propertyName): void
{
    $this->validateOnly($propertyName);
}
```

### 4. Use Loading States

```blade
{{-- ✅ Good - show loading feedback --}}
<x-admin::button :loading="$isSaving">
    Save
</x-admin::button>
```

## Testing

```php
<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use Livewire\Livewire;
use Mod\Blog\View\Modal\Admin\PostEditor;

class PostEditorTest extends TestCase
{
    public function test_creates_post(): void
    {
        Livewire::test(PostEditor::class)
            ->set('title', 'Test Post')
            ->set('content', 'Test content')
            ->set('status', 'published')
            ->call('save')
            ->assertRedirect(route('admin.blog.posts'));

        $this->assertDatabaseHas('posts', [
            'title' => 'Test Post',
        ]);
    }

    public function test_validates_required_fields(): void
    {
        Livewire::test(PostEditor::class)
            ->set('title', '')
            ->call('save')
            ->assertHasErrors(['title' => 'required']);
    }

    public function test_updates_existing_post(): void
    {
        $post = Post::factory()->create();

        Livewire::test(PostEditor::class, ['post' => $post])
            ->set('title', 'Updated Title')
            ->call('save')
            ->assertRedirect();

        $this->assertEquals('Updated Title', $post->fresh()->title);
    }
}
```

## Learn More

- [Form Components →](/packages/admin/forms)
- [HLCRF Layouts →](/packages/admin/hlcrf)
- [Livewire Documentation →](https://livewire.laravel.com)
