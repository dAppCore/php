# Front/Admin/View/Components

Class-backed Blade components for the admin panel. Registered via `<admin:xyz>` tag syntax in Boot.php.

## Components

| Component | Tag | Purpose |
|-----------|-----|---------|
| ActivityFeed | `<admin:activity-feed>` | Timeline of recent activity items with icons and timestamps |
| ActivityLog | `<admin:activity-log>` | Detailed activity log with filtering |
| Alert | `<admin:alert>` | Dismissible alert/notification banners |
| CardGrid | `<admin:card-grid>` | Responsive grid of cards |
| ClearFilters | `<admin:clear-filters>` | Button to reset active table/list filters |
| DataTable | `<admin:data-table>` | Table with columns, rows, title, empty state, and action link |
| EditableTable | `<admin:editable-table>` | Inline-editable table rows |
| Filter | `<admin:filter>` | Single filter control (select, input, etc.) |
| FilterBar | `<admin:filter-bar>` | Horizontal bar of filter controls |
| LinkGrid | `<admin:link-grid>` | Grid of navigational link cards |
| ManagerTable | `<admin:manager-table>` | CRUD management table with actions |
| Metrics | `<admin:metrics>` | Grid of metric cards (configurable columns: 2-4) |
| ProgressList | `<admin:progress-list>` | List of items with progress indicators |
| Search | `<admin:search>` | Search input with Livewire integration |
| ServiceCard | `<admin:service-card>` | Card displaying a service's status, stats, and actions |
| Sidemenu | `<admin:sidemenu>` | Sidebar navigation built from AdminMenuRegistry |
| Stats | `<admin:stats>` | Grid of stat cards (configurable columns: 2-6) |
| StatusCards | `<admin:status-cards>` | Cards showing system/service status |

All components extend `Illuminate\View\Component` and render via Blade templates in `Admin/Blade/components/`.
