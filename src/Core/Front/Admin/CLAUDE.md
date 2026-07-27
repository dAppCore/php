# Front/Admin

Admin panel frontage. Service provider that configures the `admin` middleware group, registers `<admin:xyz>` Blade tag syntax, and boots 18 class-backed view components.

## Files

- **Boot.php** -- ServiceProvider configuring the `admin` middleware stack (separate from web -- includes `auth`). Registers `admin::` Blade namespace, class component aliases (e.g., `admin-data-table`), `<admin:xyz>` tag compiler, and fires `AdminPanelBooting` lifecycle event. Binds `AdminMenuRegistry` as singleton.
- **AdminMenuRegistry.php** -- Central registry for admin sidebar navigation. Modules register `AdminMenuProvider` implementations during boot. Handles entitlement checks, permission filtering, caching (5min TTL), priority sorting, and menu structure building with groups: dashboard, agents, workspaces, services, settings, admin.
- **AdminTagCompiler.php** -- Blade precompiler for `<admin:xyz>` tags. Resolves class-backed components first (via `admin-xyz` aliases), falls back to anonymous `admin::xyz` namespace.
- **TabContext.php** -- Static context for `<admin:tabs>` to communicate selected state to child `<admin:tab.panel>` components.

## Middleware Stack

The `admin` group: EncryptCookies, AddQueuedCookiesToResponse, StartSession, ShareErrorsFromSession, ValidateCsrfToken, SubstituteBindings, SecurityHeaders, auth.

## Tag Syntax

```blade
<admin:data-table :columns="$cols" :rows="$rows" />
<admin:sidemenu />
<admin:tabs :tabs="$tabs" :selected="$current">
    <admin:tabs.panel name="general">Content</admin:tabs.panel>
</admin:tabs>
```
