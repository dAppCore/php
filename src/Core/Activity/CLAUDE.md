# Activity

Workspace-aware activity logging built on `spatie/laravel-activitylog`.

## What It Does

Wraps Spatie's activity log with automatic `workspace_id` tagging, a fluent query service, a Livewire feed component for the admin panel, and a prune command for retention management.

## Key Classes

| Class | Purpose |
|-------|---------|
| `Boot` | Registers console commands, Livewire component, and service binding via lifecycle events |
| `Activity` (model) | Extends Spatie's model with `ActivityScopes` trait. Adds `workspace_id`, `old_values`, `new_values`, `changes`, `causer_name`, `subject_name` accessors |
| `ActivityLogService` | Fluent query builder: `logFor($model)`, `logBy($user)`, `forWorkspace($ws)`, `ofType('updated')`, `search('term')`, `paginate()`, `statistics()`, `timeline()`, `prune()` |
| `LogsActivity` (trait) | Drop-in trait for models. Auto-logs dirty attributes, auto-tags `workspace_id` from model or request context, generates human descriptions |
| `ActivityScopes` (trait) | 20+ Eloquent scopes: `forWorkspace`, `forSubject`, `byCauser`, `ofType`, `betweenDates`, `today`, `lastDays`, `search`, `withChanges`, `withExistingSubject` |
| `ActivityPruneCommand` | `php artisan activity:prune [--days=N] [--dry-run]` |
| `ActivityFeed` (Livewire) | `<livewire:core.activity-feed :workspace-id="$id" />` with filters, search, pagination, detail modal |

## Public API

```php
// Make a model log activity
class Post extends Model {
    use LogsActivity;
    protected array $activityLogAttributes = ['title', 'status'];
}

// Query activities
$service = app(ActivityLogService::class);
$service->logFor($post)->lastDays(7)->paginate();
$service->forWorkspace($workspace)->ofType('deleted')->recent(10);
$service->statistics($workspace); // => [total, by_event, by_subject, by_user]
```

## Integration

- Listens to `ConsoleBooting` and `AdminPanelBooting` lifecycle events
- `LogsActivity` trait auto-detects workspace from model's `workspace_id` attribute, request `workspace_model` attribute, or authenticated user's `defaultHostWorkspace()`
- Config: `core.activity.enabled`, `core.activity.retention_days` (default 90), `core.activity.log_name`
- Override activity model in `config/activitylog.php`: `'activity_model' => Activity::class`

## Conventions

- `LogsActivity::withoutActivityLogging(fn() => ...)` to suppress logging during bulk operations
- Models can implement `customizeActivity($activity, $event)` for custom property injection
- Config properties on model: `$activityLogAttributes`, `$activityLogName`, `$activityLogEvents`, `$activityLogWorkspace`, `$activityLogOnlyDirty`
