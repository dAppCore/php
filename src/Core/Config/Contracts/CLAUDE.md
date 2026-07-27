# Config/Contracts/ — Config System Interfaces

## Interfaces

| Interface | Purpose |
|-----------|---------|
| `ConfigProvider` | Virtual configuration provider. Supplies config values at runtime without database storage. Matched against key patterns (e.g., `bio.*`). Registered via `ConfigResolver::registerProvider()`. |

Providers implement `pattern()` (wildcard key matching) and `resolve()` (returns the value for a given key, workspace, and channel context). Returns `null` to fall through to database resolution.
