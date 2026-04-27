# Config/View/Modal/Admin/ — Config Admin Livewire Components

Livewire components for the admin configuration interface.

## Components

| Component | Purpose |
|-----------|---------|
| `ConfigPanel` | Hades-only config management. Browse/search keys by category, edit values inline, toggle FINAL locks, manage system and workspace scopes. Respects parent lock enforcement. |
| `WorkspaceConfig` | Workspace-scoped settings. Hierarchical namespace navigation (cdn/bunny/storage), tab grouping by second-level prefix, value editing with inherited value display, system lock indicators. |

Both require the Tenant module for workspace support and fall back gracefully without it. `ConfigPanel` requires Hades (super-admin) access. Values are persisted via `ConfigService`.
