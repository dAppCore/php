# Core/Front Component Pattern

**Rule: Blade templates render. PHP classes compute.**

---

## The Pattern

Every component that needs PHP logic gets a backing class.

```
Component = View/Components/Name.php + Blade/components/name.blade.php
```

### Component Class (`View/Components/Name.php`)

```php
<?php

declare(strict_types=1);

namespace Core\Front\Admin\View\Components;

use Illuminate\View\Component;

class DataTable extends Component
{
    public array $columns;
    public array $rows;
    public bool $sortable;

    public function __construct(
        array $columns = [],
        array $rows = [],
        bool $sortable = false,
    ) {
        $this->columns = $columns;
        $this->rows = $rows;
        $this->sortable = $sortable;
    }

    // Computed properties / helpers for the view
    public function hasData(): bool
    {
        return !empty($this->rows);
    }

    public function render()
    {
        return view('admin::data-table');
    }
}
```

### Blade Template (`Blade/components/name.blade.php`)

```blade
@if($hasData())
    <table>
        @foreach($columns as $col)
            <th>{{ $col['label'] }}</th>
        @endforeach
        @foreach($rows as $row)
            <tr>...</tr>
        @endforeach
    </table>
@else
    <admin:empty-state />
@endif
```

**No `@php` blocks. No business logic. Just loops and conditionals on data the class provides.**

---

## What's Allowed in Blade

| Allowed | Not Allowed |
|---------|-------------|
| `@foreach`, `@if`, `@unless` | `@php` blocks |
| `{{ $variable }}` | Database queries |
| `{{ $method() }}` | Service calls |
| `@props([...])` for anonymous components | Route checks (`request()->routeIs()`) |
| Slot composition | Auth checks |
| Calling other components | Entitlement checks |

---

## Anonymous vs Class Components

**Anonymous** (no PHP class) - for pure presentation:
- `<admin:panel>` - just wraps content
- `<admin:nav-item>` - just renders a link
- `<admin:empty-state>` - just shows a message

**Class-backed** - when you need logic:
- `<admin:sidemenu>` - iterates complex nested structure
- `<admin:data-table>` - sorting, filtering, pagination
- `<admin:activity-log>` - date formatting, grouping

---

## Registration

In `Boot.php`:

```php
use Core\Front\Admin\View\Components\DataTable;
use Core\Front\Admin\View\Components\Sidemenu;

public function boot(): void
{
    // Anonymous components (just Blade files)
    Blade::anonymousComponentPath(__DIR__.'/Blade', 'admin');

    // Class-backed components
    Blade::component('admin-sidemenu', Sidemenu::class);
    Blade::component('admin-data-table', DataTable::class);
}
```

---

## Directory Structure

```
app/Core/Front/Admin/
├── Boot.php                           # Registers components
├── AdminTagCompiler.php               # <admin:xyz> syntax
├── Blade/
│   ├── components/
│   │   ├── panel.blade.php            # Anonymous (pure presentation)
│   │   ├── nav-item.blade.php         # Anonymous
│   │   ├── sidemenu.blade.php         # Class-backed template
│   │   └── data-table.blade.php       # Class-backed template
│   └── layouts/
│       └── app.blade.php
└── View/
    └── Components/
        ├── Sidemenu.php               # Sidemenu logic
        └── DataTable.php              # DataTable logic
```

---

## Audit: Completed Refactors

All components now comply with the pattern. Class-backed components:

| Component | Class | Notes |
|-----------|-------|-------|
| activity-feed | ActivityFeed.php | Icon/color extraction |
| activity-log | ActivityLog.php | Date formatting, event colors |
| alert | Alert.php | Type→config mapping |
| card-grid | CardGrid.php | Grid cols, progress colors |
| clear-filters | ClearFilters.php | Wire directive building |
| data-table | DataTable.php | Column processing |
| editable-table | EditableTable.php | Column/cell processing |
| filter | Filter.php | Wire model, options normalisation |
| filter-bar | FilterBar.php | Grid cols |
| link-grid | LinkGrid.php | Grid cols, item styling |
| manager-table | ManagerTable.php | Column processing |
| metrics | Metrics.php | Grid cols |
| progress-list | ProgressList.php | Percentage calculations |
| search | Search.php | Wire model building |
| service-card | ServiceCard.php | Service data extraction |
| sidemenu | Sidemenu.php | Menu data iteration |
| stats | Stats.php | Grid cols |
| status-cards | StatusCards.php | Grid cols, item colors |

### Acceptable `@php` Usage

Single-line prop defaults are OK in anonymous components:

```blade
@php
    $isExpanded = $expanded ?? $active;
@endphp
```

This is just a default value, not business logic.

---

## Module Integration

Modules provide **data**, Core provides **rendering**.

```blade
{{-- Module's template --}}
<admin:sidebar logo="/images/logo.svg" logoText="Hub">
    <admin:sidemenu :items="app(SidebarService::class)->build()" />
</admin:sidebar>
```

The SidebarService lives in the Module. It knows about:
- Entitlements
- Routes
- User permissions
- Badge counts

The Core component knows about:
- HTML structure
- CSS classes
- Icon rendering
- Expand/collapse behaviour

---

## Checklist for New Components

1. Does it need PHP logic beyond prop defaults?
   - Yes → Create class in `View/Components/`
   - No → Anonymous component is fine

2. Is it reusable across modules?
   - Yes → Goes in Core/Front
   - No → Goes in the Module

3. Does it fetch data?
   - Never in Core. Module passes data in.

4. Register class components in Boot.php

5. Zero `@php` blocks with business logic in Blade
