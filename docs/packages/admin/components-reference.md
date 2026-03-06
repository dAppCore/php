# Components Reference

Complete API reference for all form components in the Admin package, including prop documentation, validation rules, authorization integration, and accessibility notes.

## Overview

All form components in Core PHP:
- Wrap Flux UI components with additional features
- Support authorization via `canGate` and `canResource` props
- Include ARIA accessibility attributes
- Work seamlessly with Livewire
- Follow consistent naming conventions

## Input

Text input with various types and authorization support.

### Basic Usage

```blade
<x-forms.input
    id="title"
    wire:model="title"
    label="Title"
    placeholder="Enter title"
/>
```

### Props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `id` | string | **required** | Unique identifier for the input |
| `label` | string | `null` | Label text displayed above input |
| `helper` | string | `null` | Helper text displayed below input |
| `canGate` | string | `null` | Gate/policy ability to check |
| `canResource` | mixed | `null` | Resource to check ability against |
| `instantSave` | bool | `false` | Use `wire:model.live.debounce.500ms` |
| `type` | string | `'text'` | Input type (text, email, password, number, etc.) |
| `placeholder` | string | `null` | Placeholder text |
| `disabled` | bool | `false` | Disable the input |
| `readonly` | bool | `false` | Make input read-only |
| `required` | bool | `false` | Mark as required |
| `min` | number | `null` | Minimum value (for number inputs) |
| `max` | number | `null` | Maximum value (for number inputs) |
| `maxlength` | number | `null` | Maximum character length |

### Authorization Example

```blade
{{-- Input disabled if user cannot update the post --}}
<x-forms.input
    id="title"
    wire:model="title"
    label="Title"
    canGate="update"
    :canResource="$post"
/>
```

### Type Variants

```blade
{{-- Text input --}}
<x-forms.input id="name" label="Name" type="text" />

{{-- Email input --}}
<x-forms.input id="email" label="Email" type="email" />

{{-- Password input --}}
<x-forms.input id="password" label="Password" type="password" />

{{-- Number input --}}
<x-forms.input id="quantity" label="Quantity" type="number" min="1" max="100" />

{{-- Date input --}}
<x-forms.input id="date" label="Date" type="date" />

{{-- URL input --}}
<x-forms.input id="website" label="Website" type="url" />
```

### Instant Save Mode

```blade
{{-- Saves with 500ms debounce --}}
<x-forms.input
    id="slug"
    wire:model="slug"
    label="Slug"
    instantSave
/>
```

### Accessibility

The component automatically:
- Associates label with input via `id`
- Links error messages with `aria-describedby`
- Sets `aria-invalid="true"` when validation fails
- Includes helper text in accessible description

---

## Textarea

Multi-line text input with authorization support.

### Basic Usage

```blade
<x-forms.textarea
    id="content"
    wire:model="content"
    label="Content"
    rows="10"
/>
```

### Props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `id` | string | **required** | Unique identifier |
| `label` | string | `null` | Label text |
| `helper` | string | `null` | Helper text |
| `canGate` | string | `null` | Gate/policy ability to check |
| `canResource` | mixed | `null` | Resource for ability check |
| `instantSave` | bool | `false` | Use live debounced binding |
| `rows` | number | `3` | Number of visible rows |
| `placeholder` | string | `null` | Placeholder text |
| `disabled` | bool | `false` | Disable the textarea |
| `maxlength` | number | `null` | Maximum character length |

### Authorization Example

```blade
<x-forms.textarea
    id="bio"
    wire:model="bio"
    label="Biography"
    rows="5"
    canGate="update"
    :canResource="$profile"
/>
```

### With Character Limit

```blade
<x-forms.textarea
    id="description"
    wire:model="description"
    label="Description"
    maxlength="500"
    helper="Maximum 500 characters"
/>
```

---

## Select

Dropdown select with authorization support.

### Basic Usage

```blade
<x-forms.select
    id="status"
    wire:model="status"
    label="Status"
>
    <flux:select.option value="draft">Draft</flux:select.option>
    <flux:select.option value="published">Published</flux:select.option>
    <flux:select.option value="archived">Archived</flux:select.option>
</x-forms.select>
```

### Props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `id` | string | **required** | Unique identifier |
| `label` | string | `null` | Label text |
| `helper` | string | `null` | Helper text |
| `canGate` | string | `null` | Gate/policy ability to check |
| `canResource` | mixed | `null` | Resource for ability check |
| `instantSave` | bool | `false` | Use live binding |
| `placeholder` | string | `null` | Placeholder option text |
| `disabled` | bool | `false` | Disable the select |
| `multiple` | bool | `false` | Allow multiple selections |

### Authorization Example

```blade
<x-forms.select
    id="category"
    wire:model="category_id"
    label="Category"
    canGate="update"
    :canResource="$post"
    placeholder="Select a category..."
>
    @foreach($categories as $category)
        <flux:select.option value="{{ $category->id }}">
            {{ $category->name }}
        </flux:select.option>
    @endforeach
</x-forms.select>
```

### With Placeholder

```blade
<x-forms.select
    id="country"
    wire:model="country"
    label="Country"
    placeholder="Choose a country..."
>
    <flux:select.option value="us">United States</flux:select.option>
    <flux:select.option value="uk">United Kingdom</flux:select.option>
    <flux:select.option value="ca">Canada</flux:select.option>
</x-forms.select>
```

### Multiple Selection

```blade
<x-forms.select
    id="tags"
    wire:model="selectedTags"
    label="Tags"
    multiple
>
    @foreach($tags as $tag)
        <flux:select.option value="{{ $tag->id }}">
            {{ $tag->name }}
        </flux:select.option>
    @endforeach
</x-forms.select>
```

---

## Checkbox

Single checkbox with authorization support.

### Basic Usage

```blade
<x-forms.checkbox
    id="featured"
    wire:model="featured"
    label="Featured Post"
/>
```

### Props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `id` | string | **required** | Unique identifier |
| `label` | string | `null` | Label text (displayed inline) |
| `helper` | string | `null` | Helper text below checkbox |
| `canGate` | string | `null` | Gate/policy ability to check |
| `canResource` | mixed | `null` | Resource for ability check |
| `instantSave` | bool | `false` | Use live binding |
| `disabled` | bool | `false` | Disable the checkbox |
| `value` | string | `null` | Checkbox value (for arrays) |

### Authorization Example

```blade
<x-forms.checkbox
    id="published"
    wire:model="published"
    label="Publish immediately"
    canGate="publish"
    :canResource="$post"
/>
```

### With Helper Text

```blade
<x-forms.checkbox
    id="newsletter"
    wire:model="newsletter"
    label="Subscribe to newsletter"
    helper="Receive weekly updates about new features"
/>
```

### Checkbox Group

```blade
<fieldset>
    <legend class="font-medium mb-2">Notifications</legend>

    <x-forms.checkbox
        id="notify_email"
        wire:model="notifications"
        label="Email notifications"
        value="email"
    />

    <x-forms.checkbox
        id="notify_sms"
        wire:model="notifications"
        label="SMS notifications"
        value="sms"
    />

    <x-forms.checkbox
        id="notify_push"
        wire:model="notifications"
        label="Push notifications"
        value="push"
    />
</fieldset>
```

---

## Toggle

Switch-style toggle with authorization support.

### Basic Usage

```blade
<x-forms.toggle
    id="active"
    wire:model="active"
    label="Active"
/>
```

### Props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `id` | string | **required** | Unique identifier |
| `label` | string | `null` | Label text (displayed to the left) |
| `helper` | string | `null` | Helper text below toggle |
| `canGate` | string | `null` | Gate/policy ability to check |
| `canResource` | mixed | `null` | Resource for ability check |
| `instantSave` | bool | `false` | Use live binding |
| `disabled` | bool | `false` | Disable the toggle |

### Authorization Example

```blade
<x-forms.toggle
    id="is_admin"
    wire:model="is_admin"
    label="Administrator"
    canGate="manageRoles"
    :canResource="$user"
/>
```

### Instant Save

```blade
{{-- Toggle that saves immediately --}}
<x-forms.toggle
    id="notifications_enabled"
    wire:model="notifications_enabled"
    label="Enable Notifications"
    instantSave
/>
```

### With Helper

```blade
<x-forms.toggle
    id="two_factor"
    wire:model="two_factor_enabled"
    label="Two-Factor Authentication"
    helper="Add an extra layer of security to your account"
/>
```

---

## Button

Action button with variants and authorization support.

### Basic Usage

```blade
<x-forms.button type="submit">
    Save Changes
</x-forms.button>
```

### Props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `variant` | string | `'primary'` | Button style variant |
| `type` | string | `'submit'` | Button type (submit, button, reset) |
| `canGate` | string | `null` | Gate/policy ability to check |
| `canResource` | mixed | `null` | Resource for ability check |
| `disabled` | bool | `false` | Disable the button |
| `loading` | bool | `false` | Show loading state |

### Variants

```blade
{{-- Primary (default) --}}
<x-forms.button variant="primary">Primary</x-forms.button>

{{-- Secondary --}}
<x-forms.button variant="secondary">Secondary</x-forms.button>

{{-- Danger --}}
<x-forms.button variant="danger">Delete</x-forms.button>

{{-- Ghost --}}
<x-forms.button variant="ghost">Cancel</x-forms.button>
```

### Authorization Example

```blade
{{-- Button disabled if user cannot delete --}}
<x-forms.button
    variant="danger"
    canGate="delete"
    :canResource="$post"
    wire:click="delete"
>
    Delete Post
</x-forms.button>
```

### With Loading State

```blade
<x-forms.button type="submit" wire:loading.attr="disabled">
    <span wire:loading.remove>Save</span>
    <span wire:loading>Saving...</span>
</x-forms.button>
```

### As Link

```blade
<x-forms.button
    variant="secondary"
    type="button"
    onclick="window.location.href='{{ route('admin.posts') }}'"
>
    Cancel
</x-forms.button>
```

---

## Authorization Props Reference

All form components support authorization through consistent props.

### How Authorization Works

When `canGate` and `canResource` are provided, the component checks if the authenticated user can perform the specified ability on the resource:

```php
// Equivalent PHP check
auth()->user()?->can($canGate, $canResource)
```

If the check fails, the component is **disabled** (not hidden).

### Props

| Prop | Type | Description |
|------|------|-------------|
| `canGate` | string | The ability/gate name to check (e.g., `'update'`, `'delete'`, `'publish'`) |
| `canResource` | mixed | The resource to check the ability against (usually a model instance) |

### Examples

**Basic Policy Check:**
```blade
<x-forms.input
    id="title"
    wire:model="title"
    canGate="update"
    :canResource="$post"
/>
```

**Multiple Components with Same Auth:**
```blade
@php $canEdit = auth()->user()?->can('update', $post); @endphp

<x-forms.input id="title" wire:model="title" :disabled="!$canEdit" />
<x-forms.textarea id="content" wire:model="content" :disabled="!$canEdit" />
<x-forms.button type="submit" :disabled="!$canEdit">Save</x-forms.button>
```

**Combining with Blade Directives:**
```blade
@can('update', $post)
    <x-forms.input id="title" wire:model="title" />
    <x-forms.button type="submit">Save</x-forms.button>
@else
    <p>You do not have permission to edit this post.</p>
@endcan
```

### Defining Policies

```php
<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    public function update(User $user, Post $post): bool
    {
        return $user->id === $post->author_id
            || $user->hasRole('editor');
    }

    public function delete(User $user, Post $post): bool
    {
        return $user->hasRole('admin');
    }

    public function publish(User $user, Post $post): bool
    {
        return $user->hasPermission('posts.publish')
            && $post->status === 'draft';
    }
}
```

---

## Accessibility Notes

### ARIA Attributes

All components automatically include appropriate ARIA attributes:

| Attribute | Usage |
|-----------|-------|
| `aria-labelledby` | Links to label element |
| `aria-describedby` | Links to helper text and error messages |
| `aria-invalid` | Set to `true` when validation fails |
| `aria-required` | Set when field is required |
| `aria-disabled` | Set when field is disabled |

### Label Association

Labels are automatically associated with inputs via the `id` prop:

```blade
<x-forms.input id="email" label="Email Address" />

{{-- Renders as: --}}
<flux:field>
    <flux:label for="email">Email Address</flux:label>
    <flux:input id="email" name="email" />
</flux:field>
```

### Error Announcements

Validation errors are linked to inputs and announced to screen readers:

```blade
{{-- Component renders error with aria-describedby link --}}
<flux:error name="email" />

{{-- Screen readers announce: "Email is required" --}}
```

### Focus Management

- Tab order follows visual order
- Focus states are clearly visible
- Error focus moves to first invalid field

### Keyboard Support

| Component | Keyboard Support |
|-----------|------------------|
| Input | Standard text input |
| Textarea | Standard multiline |
| Select | Arrow keys, Enter, Escape |
| Checkbox | Space to toggle |
| Toggle | Space to toggle, Arrow keys |
| Button | Enter/Space to activate |

---

## Validation Integration

### Server-Side Validation

Components automatically display Laravel validation errors:

```php
// In Livewire component
protected array $rules = [
    'title' => 'required|max:255',
    'content' => 'required',
    'status' => 'required|in:draft,published',
];

public function save(): void
{
    $this->validate();
    // Errors automatically shown on components
}
```

### Real-Time Validation

```php
public function updated($propertyName): void
{
    $this->validateOnly($propertyName);
}
```

```blade
{{-- Shows validation error as user types --}}
<x-forms.input
    id="email"
    wire:model.live="email"
    label="Email"
/>
```

### Custom Error Messages

```php
protected array $messages = [
    'title.required' => 'Please enter a post title.',
    'content.required' => 'Post content cannot be empty.',
];
```

---

## Complete Form Example

```blade
<form wire:submit="save" class="space-y-6">
    {{-- Title --}}
    <x-forms.input
        id="title"
        wire:model="title"
        label="Title"
        placeholder="Enter post title"
        canGate="update"
        :canResource="$post"
    />

    {{-- Slug with instant save --}}
    <x-forms.input
        id="slug"
        wire:model="slug"
        label="Slug"
        helper="URL-friendly version of the title"
        instantSave
        canGate="update"
        :canResource="$post"
    />

    {{-- Content --}}
    <x-forms.textarea
        id="content"
        wire:model="content"
        label="Content"
        rows="15"
        placeholder="Write your content here..."
        canGate="update"
        :canResource="$post"
    />

    {{-- Category --}}
    <x-forms.select
        id="category_id"
        wire:model="category_id"
        label="Category"
        placeholder="Select a category..."
        canGate="update"
        :canResource="$post"
    >
        @foreach($categories as $category)
            <flux:select.option value="{{ $category->id }}">
                {{ $category->name }}
            </flux:select.option>
        @endforeach
    </x-forms.select>

    {{-- Status --}}
    <x-forms.select
        id="status"
        wire:model="status"
        label="Status"
        canGate="update"
        :canResource="$post"
    >
        <flux:select.option value="draft">Draft</flux:select.option>
        <flux:select.option value="published">Published</flux:select.option>
        <flux:select.option value="archived">Archived</flux:select.option>
    </x-forms.select>

    {{-- Featured toggle --}}
    <x-forms.toggle
        id="featured"
        wire:model="featured"
        label="Featured Post"
        helper="Display prominently on the homepage"
        canGate="update"
        :canResource="$post"
    />

    {{-- Newsletter checkbox --}}
    <x-forms.checkbox
        id="notify_subscribers"
        wire:model="notify_subscribers"
        label="Notify subscribers"
        helper="Send email notification when published"
        canGate="publish"
        :canResource="$post"
    />

    {{-- Actions --}}
    <div class="flex gap-3 pt-4 border-t">
        <x-forms.button
            type="submit"
            canGate="update"
            :canResource="$post"
        >
            Save Changes
        </x-forms.button>

        <x-forms.button
            variant="secondary"
            type="button"
            onclick="window.location.href='{{ route('admin.posts') }}'"
        >
            Cancel
        </x-forms.button>

        @can('delete', $post)
            <x-forms.button
                variant="danger"
                type="button"
                wire:click="delete"
                wire:confirm="Are you sure you want to delete this post?"
                class="ml-auto"
            >
                Delete
            </x-forms.button>
        @endcan
    </div>
</form>
```

## Learn More

- [Form Components Guide](/packages/admin/forms)
- [Authorization](/packages/admin/authorization)
- [Creating Admin Panels](/packages/admin/creating-admin-panels)
- [Livewire Modals](/packages/admin/modals)
