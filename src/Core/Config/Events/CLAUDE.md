# Config/Events/ — Config System Events

Events dispatched by the configuration system for reactive integration.

## Events

| Event | Fired When | Key Properties |
|-------|-----------|----------------|
| `ConfigChanged` | A config value is set or updated via `ConfigService::set()` | `keyCode`, `value`, `previousValue`, `profile`, `channelId` |
| `ConfigInvalidated` | Config cache is manually cleared | `keyCode` (null = all), `workspaceId`, `channelId`. Has `isFull()` and `affectsKey()` helpers. |
| `ConfigLocked` | A config value is locked (FINAL) | `keyCode`, `profile`, `channelId` |

Modules can listen to these events via the standard `$listens` pattern in their Boot class to react to config changes (e.g., refreshing CDN clients, flushing caches).
