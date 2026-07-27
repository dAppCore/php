# Front/Admin/Concerns

Traits for admin panel functionality.

## Files

- **HasMenuPermissions.php** -- Default implementation of `AdminMenuProvider` permission methods. Provides `menuPermissions()` (returns empty array by default), `canViewMenu()` (checks all permissions from `menuPermissions()` against the user), and `userHasPermission()` (tries Laravel Gate `can()`, then `hasPermission()`, then Spatie's `hasPermissionTo()`, falls back to allow). Include this trait in classes implementing `AdminMenuProvider` to get sensible defaults; override methods for custom logic.
