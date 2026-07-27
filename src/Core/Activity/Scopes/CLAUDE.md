# Activity/Scopes/ — Activity Query Scopes

## Traits

| Trait | Purpose |
|-------|---------|
| `ActivityScopes` | Comprehensive query scopes for activity log filtering. Includes: `forWorkspace`, `forSubject`, `forSubjectType`, `byCauser`, `byCauserId`, `ofType`, `createdEvents`, `updatedEvents`, `deletedEvents`, `betweenDates`, `today`, `lastDays`, `lastHours`, `search`, `inLog`, `withChanges`, `withExistingSubject`, `withDeletedSubject`, `newest`, `oldest`. |

Used by `Core\Activity\Models\Activity`. Workspace scoping checks both `properties->workspace_id` and subject model's `workspace_id`. Requires `spatie/laravel-activitylog`.
