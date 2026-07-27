# Blade/menu

Dropdown menu sub-components.

## Files

- **checkbox.blade.php** -- Menu item with checkbox toggle
- **group.blade.php** -- Menu item group with optional label
- **item.blade.php** -- Standard menu item (link or action)
- **radio.blade.php** -- Menu item with radio button selection
- **separator.blade.php** -- Visual separator/divider between menu groups
- **submenu.blade.php** -- Nested submenu that opens on hover/click

## Usage

```blade
<core:menu>
    <core:menu.group label="Actions">
        <core:menu.item href="/edit">Edit</core:menu.item>
        <core:menu.item href="/duplicate">Duplicate</core:menu.item>
    </core:menu.group>
    <core:menu.separator />
    <core:menu.item variant="danger">Delete</core:menu.item>
</core:menu>
```
