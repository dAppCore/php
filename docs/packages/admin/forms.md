# Form Components

The Admin package provides a comprehensive set of form components with consistent styling, validation, and authorization support.

## Overview

All form components:
- Follow consistent design patterns
- Support Laravel validation
- Include accessibility attributes (ARIA)
- Work with Livewire
- Support authorization props

## Form Group

Wrapper component for labels, inputs, and validation errors:

```blade
<x-admin::form-group
    label="Post Title"
    name="title"
    required
    help="Enter a descriptive title for your post"
>
    <x-admin::input
        name="title"
        :value="old('title', $post->title)"
        placeholder="My Amazing Post"
    />
</x-admin::form-group>
```

**Props:**
- `label` (string) - Field label
- `name` (string) - Field name for validation errors
- `required` (bool) - Show required indicator
- `help` (string) - Help text below field
- `error` (string) - Manual error message

## Input

Text input with various types:

```blade
{{-- Text input --}}
<x-admin::input
    name="title"
    label="Title"
    type="text"
    placeholder="Enter title"
    required
/>

{{-- Email input --}}
<x-admin::input
    name="email"
    label="Email"
    type="email"
    placeholder="user@example.com"
/>

{{-- Password input --}}
<x-admin::input
    name="password"
    label="Password"
    type="password"
/>

{{-- Number input --}}
<x-admin::input
    name="quantity"
    label="Quantity"
    type="number"
    min="1"
    max="100"
/>

{{-- Date input --}}
<x-admin::input
    name="published_at"
    label="Publish Date"
    type="date"
/>
```

**Props:**
- `name` (string, required) - Input name
- `label` (string) - Label text
- `type` (string) - Input type (text, email, password, number, date, etc.)
- `value` (string) - Input value
- `placeholder` (string) - Placeholder text
- `required` (bool) - Required field
- `disabled` (bool) - Disabled state
- `readonly` (bool) - Read-only state
- `min` / `max` (number) - Min/max for number inputs

## Textarea

Multi-line text input:

```blade
<x-admin::textarea
    name="content"
    label="Post Content"
    rows="10"
    placeholder="Write your content here..."
    required
/>

{{-- With character counter --}}
<x-admin::textarea
    name="description"
    label="Description"
    maxlength="500"
    rows="5"
    show-counter
/>
```

**Props:**
- `name` (string, required) - Textarea name
- `label` (string) - Label text
- `rows` (number) - Number of rows (default: 5)
- `cols` (number) - Number of columns
- `placeholder` (string) - Placeholder text
- `maxlength` (number) - Maximum character length
- `show-counter` (bool) - Show character counter
- `required` (bool) - Required field

## Select

Dropdown select:

```blade
{{-- Simple select --}}
<x-admin::select
    name="status"
    label="Status"
    :options="[
        'draft' => 'Draft',
        'published' => 'Published',
        'archived' => 'Archived',
    ]"
    :value="$post->status"
/>

{{-- With placeholder --}}
<x-admin::select
    name="category_id"
    label="Category"
    :options="$categories"
    placeholder="Select a category..."
/>

{{-- Multiple select --}}
<x-admin::select
    name="tags[]"
    label="Tags"
    :options="$tags"
    multiple
/>

{{-- Grouped options --}}
<x-admin::select
    name="location"
    label="Location"
    :options="[
        'UK' => [
            'london' => 'London',
            'manchester' => 'Manchester',
        ],
        'US' => [
            'ny' => 'New York',
            'la' => 'Los Angeles',
        ],
    ]"
/>
```

**Props:**
- `name` (string, required) - Select name
- `label` (string) - Label text
- `options` (array, required) - Options array
- `value` (mixed) - Selected value(s)
- `placeholder` (string) - Placeholder option
- `multiple` (bool) - Allow multiple selections
- `required` (bool) - Required field
- `disabled` (bool) - Disabled state

## Checkbox

Single checkbox:

```blade
<x-admin::checkbox
    name="published"
    label="Publish immediately"
    :checked="$post->published"
/>

{{-- With description --}}
<x-admin::checkbox
    name="featured"
    label="Featured Post"
    description="Display this post prominently on the homepage"
    :checked="$post->featured"
/>

{{-- Group of checkboxes --}}
<fieldset>
    <legend>Permissions</legend>

    <x-admin::checkbox
        name="permissions[]"
        label="Create Posts"
        value="posts.create"
        :checked="in_array('posts.create', $user->permissions)"
    />

    <x-admin::checkbox
        name="permissions[]"
        label="Edit Posts"
        value="posts.edit"
        :checked="in_array('posts.edit', $user->permissions)"
    />
</fieldset>
```

**Props:**
- `name` (string, required) - Checkbox name
- `label` (string) - Label text
- `value` (string) - Checkbox value
- `checked` (bool) - Checked state
- `description` (string) - Help text below checkbox
- `disabled` (bool) - Disabled state

## Toggle

Switch-style toggle:

```blade
<x-admin::toggle
    name="active"
    label="Active"
    :checked="$user->active"
/>

{{-- With colors --}}
<x-admin::toggle
    name="notifications_enabled"
    label="Email Notifications"
    description="Receive email updates about new posts"
    :checked="$user->notifications_enabled"
    color="green"
/>
```

**Props:**
- `name` (string, required) - Toggle name
- `label` (string) - Label text
- `checked` (bool) - Checked state
- `description` (string) - Help text
- `color` (string) - Toggle color (green, blue, red)
- `disabled` (bool) - Disabled state

## Button

Action buttons with variants:

```blade
{{-- Primary button --}}
<x-admin::button type="submit">
    Save Changes
</x-admin::button>

{{-- Secondary button --}}
<x-admin::button variant="secondary" href="{{ route('admin.posts.index') }}">
    Cancel
</x-admin::button>

{{-- Danger button --}}
<x-admin::button
    variant="danger"
    wire:click="delete"
    wire:confirm="Are you sure?"
>
    Delete Post
</x-admin::button>

{{-- Ghost button --}}
<x-admin::button variant="ghost">
    Reset
</x-admin::button>

{{-- Icon button --}}
<x-admin::button variant="icon" title="Edit">
    <x-icon name="pencil" />
</x-admin::button>

{{-- Loading state --}}
<x-admin::button :loading="$isLoading">
    <span wire:loading.remove>Save</span>
    <span wire:loading>Saving...</span>
</x-admin::button>
```

**Props:**
- `type` (string) - Button type (button, submit, reset)
- `variant` (string) - Style variant (primary, secondary, danger, ghost, icon)
- `href` (string) - Link URL (renders as `<a>`)
- `loading` (bool) - Show loading state
- `disabled` (bool) - Disabled state
- `size` (string) - Size (sm, md, lg)

## Authorization Props

All form components support authorization attributes:

```blade
<x-admin::button
    can="posts.create"
    :can-arguments="[$post]"
>
    Create Post
</x-admin::button>

<x-admin::input
    name="title"
    label="Title"
    readonly-unless="posts.edit"
/>

<x-admin::button
    variant="danger"
    hidden-unless="posts.delete"
    wire:click="delete"
>
    Delete
</x-admin::button>
```

**Authorization Props:**
- `can` (string) - Gate/policy check
- `can-arguments` (array) - Arguments for gate check
- `cannot` (string) - Inverse of `can`
- `hidden-unless` (string) - Hide element unless authorized
- `readonly-unless` (string) - Make readonly unless authorized
- `disabled-unless` (string) - Disable unless authorized

[Learn more about Authorization →](/packages/admin/authorization)

## Livewire Integration

All components work seamlessly with Livewire:

```blade
<form wire:submit="save">
    <x-admin::input
        name="title"
        label="Title"
        wire:model="title"
    />

    <x-admin::textarea
        name="content"
        label="Content"
        wire:model.defer="content"
    />

    <x-admin::select
        name="status"
        label="Status"
        :options="['draft' => 'Draft', 'published' => 'Published']"
        wire:model="status"
    />

    <x-admin::button type="submit" :loading="$isSaving">
        Save Post
    </x-admin::button>
</form>
```

### Real-Time Validation

```blade
<x-admin::input
    name="slug"
    label="Slug"
    wire:model.live="slug"
    wire:loading.class="opacity-50"
/>

@error('slug')
    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
@enderror
```

### Debounced Input

```blade
<x-admin::input
    name="search"
    label="Search Posts"
    wire:model.live.debounce.500ms="search"
    placeholder="Type to search..."
/>
```

## Validation

Components automatically show validation errors:

```blade
{{-- Controller validation --}}
$request->validate([
    'title' => 'required|max:255',
    'content' => 'required',
    'status' => 'required|in:draft,published',
]);

{{-- Blade template --}}
<x-admin::form-group label="Title" name="title" required>
    <x-admin::input name="title" :value="old('title')" />
</x-admin::form-group>
{{-- Validation errors automatically displayed --}}
```

### Custom Error Messages

```blade
<x-admin::form-group
    label="Email"
    name="email"
    :error="$errors->first('email')"
>
    <x-admin::input name="email" type="email" />
</x-admin::form-group>
```

## Complete Form Example

```blade
<form method="POST" action="{{ route('admin.posts.store') }}">
    @csrf

    <div class="space-y-6">
        {{-- Title --}}
        <x-admin::form-group label="Title" name="title" required>
            <x-admin::input
                name="title"
                :value="old('title', $post->title)"
                placeholder="Enter post title"
                maxlength="255"
            />
        </x-admin::form-group>

        {{-- Slug --}}
        <x-admin::form-group label="Slug" name="slug" required>
            <x-admin::input
                name="slug"
                :value="old('slug', $post->slug)"
                placeholder="post-slug"
            />
        </x-admin::form-group>

        {{-- Content --}}
        <x-admin::form-group label="Content" name="content" required>
            <x-admin::textarea
                name="content"
                :value="old('content', $post->content)"
                rows="15"
                placeholder="Write your post content..."
            />
        </x-admin::form-group>

        {{-- Status --}}
        <x-admin::form-group label="Status" name="status" required>
            <x-admin::select
                name="status"
                :options="[
                    'draft' => 'Draft',
                    'published' => 'Published',
                    'archived' => 'Archived',
                ]"
                :value="old('status', $post->status)"
            />
        </x-admin::form-group>

        {{-- Category --}}
        <x-admin::form-group label="Category" name="category_id">
            <x-admin::select
                name="category_id"
                :options="$categories"
                :value="old('category_id', $post->category_id)"
                placeholder="Select a category..."
            />
        </x-admin::form-group>

        {{-- Options --}}
        <div class="space-y-3">
            <x-admin::checkbox
                name="featured"
                label="Featured Post"
                :checked="old('featured', $post->featured)"
            />

            <x-admin::toggle
                name="comments_enabled"
                label="Enable Comments"
                :checked="old('comments_enabled', $post->comments_enabled)"
            />
        </div>

        {{-- Actions --}}
        <div class="flex gap-3">
            <x-admin::button type="submit">
                Save Post
            </x-admin::button>

            <x-admin::button
                variant="secondary"
                href="{{ route('admin.posts.index') }}"
            >
                Cancel
            </x-admin::button>

            <x-admin::button
                variant="danger"
                hidden-unless="posts.delete"
                wire:click="delete"
                wire:confirm="Delete this post permanently?"
            >
                Delete
            </x-admin::button>
        </div>
    </div>
</form>
```

## Styling

Components use Tailwind CSS and can be customized:

```blade
<x-admin::input
    name="title"
    label="Title"
    class="font-mono"
    input-class="bg-gray-50"
/>
```

### Custom Wrapper Classes

```blade
<x-admin::form-group
    label="Title"
    name="title"
    wrapper-class="max-w-xl"
>
    <x-admin::input name="title" />
</x-admin::form-group>
```

## Best Practices

### 1. Always Use Form Groups

```blade
{{-- ✅ Good - wrapped in form-group --}}
<x-admin::form-group label="Title" name="title" required>
    <x-admin::input name="title" />
</x-admin::form-group>

{{-- ❌ Bad - no form-group --}}
<x-admin::input name="title" label="Title" />
```

### 2. Use Old Values

```blade
{{-- ✅ Good - preserves input on validation errors --}}
<x-admin::input
    name="title"
    :value="old('title', $post->title)"
/>

{{-- ❌ Bad - loses input on validation errors --}}
<x-admin::input
    name="title"
    :value="$post->title"
/>
```

### 3. Provide Helpful Placeholders

```blade
{{-- ✅ Good - clear placeholder --}}
<x-admin::input
    name="slug"
    placeholder="post-slug-example"
/>

{{-- ❌ Bad - vague placeholder --}}
<x-admin::input
    name="slug"
    placeholder="Enter slug"
/>
```

### 4. Use Authorization Props

```blade
{{-- ✅ Good - respects permissions --}}
<x-admin::button
    variant="danger"
    hidden-unless="posts.delete"
>
    Delete
</x-admin::button>
```

## Learn More

- [Livewire Modals →](/packages/admin/modals)
- [Authorization →](/packages/admin/authorization)
- [HLCRF Layouts →](/packages/admin/hlcrf)
