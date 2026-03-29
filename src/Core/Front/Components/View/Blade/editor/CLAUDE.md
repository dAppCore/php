# Blade/editor

Rich text editor sub-components.

## Files

- **button.blade.php** -- Toolbar action button (bold, italic, link, etc.)
- **content.blade.php** -- Editable content area (the actual rich text editing surface)
- **toolbar.blade.php** -- Editor toolbar container wrapping action buttons

## Usage

```blade
<core:editor>
    <core:editor.toolbar>
        <core:editor.button action="bold" icon="bold" />
        <core:editor.button action="italic" icon="italic" />
        <core:editor.button action="link" icon="link" />
    </core:editor.toolbar>
    <core:editor.content wire:model="body" />
</core:editor>
```
