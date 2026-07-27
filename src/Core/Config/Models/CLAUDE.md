# Config/Models/ — Config Eloquent Models

Eloquent models implementing the four-layer hierarchical configuration system.

## Models

| Model | Table | Purpose |
|-------|-------|---------|
| `ConfigKey` | `config_keys` | M1 layer — defines what keys exist. Dot-notation codes, typed (`ConfigType`), categorised. Supports sensitive flag for auto-encryption. Hierarchical parent/child grouping. |
| `ConfigProfile` | `config_profiles` | M2 layer — groups values at a scope level (system/org/workspace). Inherits from parent profiles. Soft-deletable. |
| `ConfigValue` | `config_values` | Junction table linking profiles to keys with actual values. `locked` flag implements FINAL (prevents child override). Auto-encrypts sensitive keys. Invalidates resolver hash on write. |
| `ConfigVersion` | `config_versions` | Point-in-time snapshots for version history and rollback. Immutable (no `updated_at`). Stores JSON snapshot of all values. |
| `Channel` | `config_channels` | Context dimension (web, api, mobile, instagram, etc.). Hierarchical inheritance chain with cycle detection. System or workspace-scoped. |
| `ConfigResolved` | `config_resolved` | Materialised READ table — all lookups hit this directly. No computation at read time. Populated by the `prime` operation. Composite key (workspace_id, channel_id, key_code). |

## Resolution Flow

```
ConfigService::get() → ConfigResolved (fast lookup)
                     → miss: ConfigResolver computes from ConfigValue chain
                     → stores result back to ConfigResolved + in-memory hash
```
