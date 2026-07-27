# Front/Admin/Contracts

Interfaces for the admin menu system.

## Files

- **AdminMenuProvider.php** -- Interface for modules contributing admin sidebar items. Defines priority constants (FIRST=0 through LAST=90), `adminMenuItems()` returning registration arrays with group/priority/entitlement/permissions/item closure, `menuPermissions()` for provider-level permission requirements, and `canViewMenu()` for custom access logic. Items are lazy-evaluated -- closures only called after permission checks pass.

- **DynamicMenuProvider.php** -- Interface for providers supplying uncached, real-time menu items (e.g., notification counts, recent items). `dynamicMenuItems()` is called every request and merged after static cache retrieval. `dynamicCacheKey()` can invalidate static cache when dynamic state changes significantly.

## Menu Groups

`dashboard` | `workspaces` | `services` | `settings` | `admin`

## Priority Constants

PRIORITY_FIRST(0), PRIORITY_HIGH(10), PRIORITY_ABOVE_NORMAL(20), PRIORITY_NORMAL(50), PRIORITY_BELOW_NORMAL(70), PRIORITY_LOW(80), PRIORITY_LAST(90).
