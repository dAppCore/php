# Blade/tab

Tab sub-components for the `<core:tabs>` system.

## Files

- **group.blade.php** -- Tab group container. Wraps tab triggers and manages selected state.
- **panel.blade.php** -- Tab content panel. Shows/hides based on which tab is selected.

## Usage

```blade
<core:tab.group>
    <core:tab name="general">General</core:tab>
    <core:tab name="advanced">Advanced</core:tab>
</core:tab.group>
<core:tab.panel name="general">General content</core:tab.panel>
<core:tab.panel name="advanced">Advanced content</core:tab.panel>
```
