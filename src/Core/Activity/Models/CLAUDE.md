# Activity/Models/ — Activity Log Model

## Models

| Model | Extends | Purpose |
|-------|---------|---------|
| `Activity` | `Spatie\Activitylog\Models\Activity` | Extended activity model with workspace-aware scopes via the `ActivityScopes` trait. Adds query scopes for filtering by workspace, subject, causer, event type, date range, and search. |

Configure as the activity model in `config/activitylog.php`:
```php
'activity_model' => \Core\Activity\Models\Activity::class,
```

Requires `spatie/laravel-activitylog`.
