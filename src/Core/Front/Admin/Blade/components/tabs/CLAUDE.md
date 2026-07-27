# Front/Admin/Blade/components/tabs

Tab panel sub-component for admin tabs.

## Files

- **panel.blade.php** -- Individual tab panel that auto-detects selected state from `TabContext::$selected`. Wraps `<core:tab.panel>` with automatic selection.
  - Props: `name` (string, required) -- must match the tab key
  - Reads `\Core\Front\Admin\TabContext::$selected` to determine visibility

## Usage

```blade
<admin:tabs :tabs="$tabs" :selected="$currentTab">
    <admin:tabs.panel name="general">
        General settings content
    </admin:tabs.panel>
    <admin:tabs.panel name="advanced">
        Advanced settings content
    </admin:tabs.panel>
</admin:tabs>
```
