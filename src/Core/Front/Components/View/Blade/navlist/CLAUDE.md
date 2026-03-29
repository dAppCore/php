# Blade/navlist

Navigation list sub-components for sidebar/panel navigation.

## Files

- **group.blade.php** -- Navigation group with optional heading label. Groups related nav items together with visual separation.
- **item.blade.php** -- Individual navigation item with label, href, icon, and active state.

## Usage

```blade
<core:navlist>
    <core:navlist.group heading="Main">
        <core:navlist.item href="/dashboard" icon="home" :active="true">Dashboard</core:navlist.item>
        <core:navlist.item href="/settings" icon="gear">Settings</core:navlist.item>
    </core:navlist.group>
</core:navlist>
```
