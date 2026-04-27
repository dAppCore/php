# Front/Admin/Blade/components

Anonymous Blade components for the admin panel. Used via `<admin:xyz>` tag syntax.

## Components

| Component | Purpose |
|-----------|---------|
| action-link | Styled link for table row actions |
| activity-feed | Template for ActivityFeed class component |
| activity-log | Template for ActivityLog class component |
| alert | Template for Alert class component |
| card-grid | Template for CardGrid class component |
| clear-filters | Template for ClearFilters class component |
| data-table | Template for DataTable class component |
| editable-table | Template for EditableTable class component |
| empty-state | Empty state placeholder with icon and message |
| entitlement-gate | Conditionally renders content based on workspace entitlements |
| filter / filter-bar | Template for Filter/FilterBar class components |
| flash | Session flash message display |
| header | Page header with breadcrumbs and actions |
| link-grid | Template for LinkGrid class component |
| manager-table | Template for ManagerTable class component |
| metric-card / metrics | Individual metric card and grid template |
| module | Module wrapper with loading states |
| nav-group / nav-item / nav-link / nav-menu / nav-panel | Sidebar navigation primitives |
| page-header | Page title bar with optional subtitle and actions |
| panel | Content panel with optional header/footer |
| progress-list | Template for ProgressList class component |
| search | Template for Search class component |
| service-card / service-cards | Service overview cards |
| sidebar / sidemenu | Sidebar shell and menu template |
| stat-card / stats | Individual stat card and grid template |
| status-cards | Template for StatusCards class component |
| tabs | Tab navigation wrapper using `<core:tabs>` |
| workspace-card | Workspace overview card |

Most are templates for the class-backed components in `View/Components/`. A few are standalone anonymous components (empty-state, entitlement-gate, flash, nav-*, page-header, panel, workspace-card).
