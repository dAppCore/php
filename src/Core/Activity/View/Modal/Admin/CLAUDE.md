# Activity/View/Modal/Admin/ — Activity Feed Livewire Component

## Components

| Component | Purpose |
|-----------|---------|
| `ActivityFeed` | Livewire component for displaying activity logs in the admin panel. Paginated list with URL-bound filters (causer, subject type, event type, date range, search). Supports workspace scoping and optional polling for real-time updates. |

Usage: `<livewire:core.activity-feed />` or `<livewire:core.activity-feed :workspace-id="$workspace->id" poll="10s" />`

Requires `spatie/laravel-activitylog`.
