# Activity/Services/ — Activity Log Service

## Services

| Service | Purpose |
|---------|---------|
| `ActivityLogService` | Fluent interface for querying and managing activity logs. Methods: `logFor($model)`, `logBy($user)`, `forWorkspace($workspace)`, `recent()`, `search($term)`. Chainable query builder with workspace awareness. |

Provides the business logic layer over Spatie's activity log. Used by the `ActivityFeed` Livewire component and available for injection throughout the application.

Requires `spatie/laravel-activitylog`.
