# Front

UI layer: admin panel, web frontage, Blade components, layouts, and tag compilers.

## What It Does

Three distinct frontages sharing a component library:

1. **Admin** (`Admin/`) -- Admin dashboard with its own middleware group, menu registry, 50+ Blade components, and `<admin:xyz>` tag compiler
2. **Web** (`Web/`) -- Public-facing pages with `web` middleware, `<web:xyz>` tag compiler, domain resolution
3. **Components** (`Components/`) -- Programmatic component library (Card, Heading, NavList, Layout, etc.) implementing `Htmlable` for use by MCP tools and agents

## Directory Structure

```
Front/
  Controller.php          -- Abstract base controller
  Admin/
    Boot.php              -- Admin ServiceProvider (middleware, components, tag compiler)
    AdminMenuRegistry.php -- Menu builder with entitlements, permissions, caching
    AdminTagCompiler.php  -- <admin:xyz> Blade precompiler
    TabContext.php         -- Tab state management
    Contracts/             -- AdminMenuProvider, DynamicMenuProvider interfaces
    Support/               -- MenuItemBuilder, MenuItemGroup
    Concerns/              -- HasMenuPermissions trait
    Validation/            -- IconValidator (Font Awesome Pro validation)
    View/Components/       -- 18 class-backed components (DataTable, Stats, Metrics, etc.)
    Blade/
      components/          -- 30+ anonymous Blade components
      layouts/app.blade.php
  Web/
    Boot.php              -- Web ServiceProvider (middleware, tag compiler, lifecycle fire)
    WebTagCompiler.php    -- <web:xyz> Blade precompiler
    Middleware/
      FindDomainRecord.php   -- Resolves domain to workspace
      ResilientSession.php   -- Handles session issues gracefully
      RedirectIfAuthenticated.php
    Blade/
      components/          -- nav-item, page
      layouts/app.blade.php
  Components/
    Component.php          -- Abstract base: fluent attr/class API, Htmlable
    Card.php, Heading.php, NavList.php, Layout.php
    CoreTagCompiler.php    -- <core:xyz> tag compiler
    View/Blade/            -- 20+ component templates (forms, table, autocomplete, avatar, etc.)
  Tests/Unit/              -- DeviceDetectionServiceTest
```

## Admin Menu System

`AdminMenuRegistry` is the central hub:
- Modules implement `AdminMenuProvider` interface and register via `$registry->register($provider)`
- Items grouped into: `dashboard`, `agents`, `workspaces`, `services`, `settings`, `admin`
- Entitlement checks via `EntitlementService::can()`
- Permission checks via Laravel's `$user->can()`
- `DynamicMenuProvider` for runtime items (never cached)
- Cached with configurable TTL, invalidatable per workspace/user
- Icon validation against Font Awesome Pro

## Middleware Groups

**Admin** (`admin`): EncryptCookies, Session, CSRF, Bindings, SecurityHeaders, `auth`
**Web** (`web`): EncryptCookies, Session, ResilientSession, CSRF, Bindings, SecurityHeaders, FindDomainRecord

## Tag Compilers

Custom Blade precompilers enable `<admin:xyz>`, `<web:xyz>`, and `<core:xyz>` syntax (same pattern as `<flux:xyz>`).

## Programmatic Components

`Component` base class provides fluent API for building HTML without Blade:
```php
Card::make()->class('p-4')->attr('data-id', 42)->render()
```
Used by MCP tools and agents to compose UIs programmatically.

## Integration

- Admin Boot fires `AdminPanelBooting` lifecycle event
- Web Boot fires `WebRoutesRegistering` via `$app->booted()` callback
- `livewire` aliased to `admin` for Flux Pro compatibility
- All admin components prefixed `admin-` (e.g., `<x-admin-data-table>`)
