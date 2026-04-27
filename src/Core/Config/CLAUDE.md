# Config

Database-backed configuration with scoping, versioning, profiles, and admin UI.

## What It Does

Replaces/supplements Laravel's file-based config with a DB-backed system supporting:
- Hierarchical scope resolution (global -> workspace -> user)
- Configuration profiles (sets of values that can be switched)
- Version history with diffs
- Sensitive value encryption
- Import/export (JSON/YAML)
- Livewire admin panels
- Event-driven invalidation

## Key Classes

| Class | Purpose |
|-------|---------|
| `Boot` | Listens to `AdminPanelBooting` and `ConsoleBooting` for registration |
| `ConfigService` | Primary API: `get()`, `set()`, `isConfigured()`, plus scope-aware resolution |
| `ConfigResolver` | Resolves values through scope hierarchy: user -> workspace -> global -> default |
| `ConfigResult` | DTO wrapping resolved value with metadata (source scope, profile, etc.) |
| `ConfigVersioning` | Tracks changes with diffs between versions |
| `VersionDiff` | Computes and formats diffs between config versions |
| `ConfigExporter` | Export/import config as JSON/YAML |
| `ImportResult` | DTO for import operation results |

## Models

| Model | Table | Purpose |
|-------|-------|---------|
| `ConfigKey` | `config_keys` | Key definitions with type, default, validation rules, `is_sensitive` flag |
| `ConfigValue` | `config_values` | Actual values scoped by type (global/workspace/user) |
| `ConfigProfile` | `config_profiles` | Named sets of config values (soft-deletable) |
| `ConfigVersion` | `config_versions` | Version history snapshots |
| `ConfigResolved` | -- | Value object for resolved config |
| `Channel` | -- | Notification channel config |

## Enums

- `ConfigType` -- Value types (string, int, bool, json, etc.)
- `ScopeType` -- Resolution scopes (global, workspace, user)

## Events

- `ConfigChanged` -- Fired when any config value changes
- `ConfigInvalidated` -- Fired when cache needs clearing
- `ConfigLocked` -- Fired when a config key is locked

## Console Commands

- `config:prime` -- Pre-populate config cache
- `config:list` -- List all config keys and values
- `config:version` -- Show version history
- `config:import` -- Import config from file
- `config:export` -- Export config to file

## Admin UI

- `ConfigPanel` (Livewire) -- General config editing panel
- `WorkspaceConfig` (Livewire) -- Workspace-specific config panel
- Routes registered under admin prefix

## Integration

- `ConfigService` is used by other subsystems (e.g., `BunnyCdnService` reads CDN credentials via `$this->config->get('cdn.bunny.api_key')`)
- Sensitive keys (`is_sensitive = true`) are encrypted at rest
- Seeder: `ConfigKeySeeder` populates default keys
- 4 migrations covering base tables, soft deletes, versions, and sensitive flag
