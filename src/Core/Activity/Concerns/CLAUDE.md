# Activity/Concerns/ — Activity Logging Trait

## Traits

| Trait | Purpose |
|-------|---------|
| `LogsActivity` | Drop-in trait for models that should log changes. Wraps `spatie/laravel-activitylog` with sensible defaults: auto workspace_id tagging, dirty-only logging, empty log suppression. |

## Configuration via Model Properties

- `$activityLogAttributes` — array of attributes to log (default: all dirty)
- `$activityLogName` — custom log name
- `$activityLogEvents` — events to log (default: created, updated, deleted)
- `$activityLogWorkspace` — include workspace_id (default: true)
- `$activityLogOnlyDirty` — only log changed attributes (default: true)

Static helpers: `activityLoggingEnabled()`, `withoutActivityLogging(callable)`.
