# Config/View/Blade/admin/ — Config Admin Blade Templates

Blade templates for the admin configuration panel.

## Templates

| File | Purpose |
|------|---------|
| `config-panel.blade.php` | Full config management panel — browse keys by category, edit values, toggle locks, manage system vs workspace scopes. Used by `ConfigPanel` Livewire component. |
| `workspace-config.blade.php` | Workspace-specific config panel — hierarchical namespace navigation, tab grouping, value editing with system inheritance display. Used by `WorkspaceConfig` Livewire component. |

Both templates use the `hub::admin.layouts.app` layout and are rendered via the `core.config::admin.*` view namespace.
