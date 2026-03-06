# HLCRF Layout System

HLCRF (Header-Left-Content-Right-Footer) is a hierarchical, composable layout system for building complex layouts with infinite nesting. It provides flexible region-based layouts without restricting HTML structure.

## Overview

Traditional Blade layouts force rigid inheritance hierarchies. HLCRF allows components to declare which layout regions they contribute to, enabling composition without structural constraints.

**Use Cases:**
- Admin panels and dashboards
- Content management interfaces
- Marketing landing pages
- E-commerce product pages
- Documentation sites
- Any complex multi-region layout

### Traditional Blade Layouts

```blade
{{-- layouts/admin.blade.php --}}
<html>
<body>
    <header>@yield('header')</header>
    <aside>@yield('sidebar')</aside>
    <main>@yield('content')</main>
</body>
</html>

{{-- pages/dashboard.blade.php --}}
@extends('layouts.admin')

@section('header')
    Dashboard Header
@endsection

@section('content')
    Dashboard Content
@endsection
```

**Problems:**
- Rigid structure
- Deep nesting
- Hard to compose sections
- Components can't contribute to multiple regions

### HLCRF Approach

```blade
{{-- pages/dashboard.blade.php --}}
<x-hlcrf::layout>
    <x-hlcrf::header>
        Dashboard Header
    </x-hlcrf::header>

    <x-hlcrf::left>
        Navigation Menu
    </x-hlcrf::left>

    <x-hlcrf::content>
        Dashboard Content
    </x-hlcrf::content>

    <x-hlcrf::right>
        Sidebar Widgets
    </x-hlcrf::right>
</x-hlcrf::layout>
```

**Benefits:**
- Declarative region definition
- Easy composition
- Components contribute to any region
- No structural constraints

## Layout Regions

HLCRF defines five semantic regions:

```
┌────────────────────────────────────┐
│           Header (H)               │
├──────┬─────────────────┬───────────┤
│      │                 │           │
│ Left │   Content (C)   │  Right    │
│ (L)  │                 │   (R)     │
│      │                 │           │
├──────┴─────────────────┴───────────┤
│          Footer (F)                │
└────────────────────────────────────┘
```

### Self-Documenting IDs

Every HLCRF element receives a unique ID that describes its position in the DOM tree. This makes debugging, styling, and testing trivial:

**ID Format:** `{Region}-{Index}-{NestedRegion}-{NestedIndex}...`

**Examples:**
- `H-0` = First header element
- `L-1` = Second left sidebar element (0-indexed)
- `C-R-2` = Content region → Right sidebar → Third element
- `C-L-0-R-1` = Content → Left → First element → Right → Second element

**Region Letters:**
- `H` = Header
- `L` = Left
- `C` = Content
- `R` = Right
- `F` = Footer

**Benefits:**
1. **Instant debugging** - See element position from DevTools
2. **Precise CSS targeting** - No class soup needed
3. **Test selectors** - Stable IDs for E2E tests
4. **Documentation** - DOM structure is self-explanatory

```html
<!-- Real-world example -->
<div id="H-0" class="hlcrf-header">
    <nav>Global Navigation</nav>
</div>

<div id="C-0" class="hlcrf-content">
    <div id="C-L-0" class="hlcrf-left">
        <!-- This is: Content → Left → First element -->
        <aside>Sidebar</aside>
    </div>

    <div id="C-C-0" class="hlcrf-content">
        <!-- This is: Content → Content (nested) → First element -->
        <article>Main content</article>
    </div>

    <div id="C-R-0" class="hlcrf-right">
        <!-- This is: Content → Right → First element -->
        <aside>Widgets</aside>
    </div>
</div>
```

**CSS Examples:**

```css
/* Target specific nested elements */
#C-R-2 { width: 300px; }

/* Target all right sidebars at any depth */
[id$="-R-0"] { background: #f9f9f9; }

/* Target deeply nested content regions */
[id*="-C-"][id*="-C-"] { padding: 2rem; }

/* Target second header element anywhere */
[id^="H-1"], [id*="-H-1"] { font-weight: bold; }
```

### Header Region

Top section for navigation, branding, global actions:

```blade
<x-hlcrf::header>
    <nav class="flex items-center justify-between">
        <div class="logo">
            <img src="/logo.png" alt="Logo">
        </div>

        <div class="nav-links">
            <a href="/dashboard">Dashboard</a>
            <a href="/settings">Settings</a>
        </div>

        <div class="user-menu">
            <x-user-dropdown />
        </div>
    </nav>
</x-hlcrf::header>
```

### Left Region

Sidebar navigation, filters, secondary navigation:

```blade
<x-hlcrf::left>
    <aside class="w-64">
        <nav class="space-y-2">
            <a href="/posts" class="block px-4 py-2">Posts</a>
            <a href="/categories" class="block px-4 py-2">Categories</a>
            <a href="/tags" class="block px-4 py-2">Tags</a>
        </nav>
    </aside>
</x-hlcrf::left>
```

### Content Region

Main content area:

```blade
<x-hlcrf::content>
    <div class="container mx-auto">
        <h1>Dashboard</h1>

        <div class="grid grid-cols-3 gap-4">
            <x-stat-card title="Posts" :value="$postCount" />
            <x-stat-card title="Users" :value="$userCount" />
            <x-stat-card title="Comments" :value="$commentCount" />
        </div>

        <div class="mt-8">
            <x-recent-activity :activities="$activities" />
        </div>
    </div>
</x-hlcrf::content>
```

### Right Region

Contextual help, related actions, widgets:

```blade
<x-hlcrf::right>
    <aside class="w-80 space-y-4">
        <x-help-widget>
            <h3>Getting Started</h3>
            <p>Learn how to create your first post...</p>
        </x-help-widget>

        <x-quick-actions-widget>
            <x-button href="/posts/create">New Post</x-button>
            <x-button href="/categories/create">New Category</x-button>
        </x-quick-actions-widget>
    </aside>
</x-hlcrf::right>
```

### Footer Region

Copyright, links, status information:

```blade
<x-hlcrf::footer>
    <footer class="text-center text-sm text-gray-600">
        &copy; 2026 Your Company. All rights reserved.
        <span class="mx-2">|</span>
        <a href="/privacy">Privacy</a>
        <span class="mx-2">|</span>
        <a href="/terms">Terms</a>
    </footer>
</x-hlcrf::footer>
```

## Component Composition

### Multiple Components Contributing

Components can contribute to multiple regions:

```blade
<x-hlcrf::layout>
    {{-- Page header --}}
    <x-hlcrf::header>
        <x-page-header title="Blog Posts" />
    </x-hlcrf::header>

    {{-- Filters sidebar --}}
    <x-hlcrf::left>
        <x-post-filters />
    </x-hlcrf::left>

    {{-- Main content --}}
    <x-hlcrf::content>
        <x-post-list :posts="$posts" />
    </x-hlcrf::content>

    {{-- Help sidebar --}}
    <x-hlcrf::right>
        <x-post-help />
        <x-post-stats :posts="$posts" />
    </x-hlcrf::right>
</x-hlcrf::layout>
```

### Nested Layouts

HLCRF layouts can be nested infinitely. Each element receives a unique, self-documenting ID that describes its position in the DOM tree:

```blade
{{-- components/post-editor.blade.php --}}
<div class="post-editor">
    {{-- Nested HLCRF layout inside a parent layout --}}
    <x-hlcrf::layout>
        {{-- Editor toolbar goes to header --}}
        <x-hlcrf::header>
            <x-editor-toolbar />
        </x-hlcrf::header>

        {{-- Content editor --}}
        <x-hlcrf::content>
            <textarea name="content">{{ $post->content }}</textarea>
        </x-hlcrf::content>

        {{-- Metadata sidebar --}}
        <x-hlcrf::right>
            <x-post-metadata :post="$post" />
        </x-hlcrf::right>
    </x-hlcrf::layout>
</div>
```

**Generated IDs:**
```html
<div id="H-0"><!-- First Header element --></div>
<div id="L-0"><!-- First Left element --></div>
<div id="C-0"><!-- First Content element --></div>
<div id="C-R-2"><!-- Content → Right, 3rd element (0-indexed: 2) --></div>
<div id="C-L-0-R-1"><!-- Content → Left → First → Right → Second --></div>
```

The ID format follows the pattern:
- Single letter = region type (`H`=Header, `L`=Left, `C`=Content, `R`=Right, `F`=Footer)
- Number = index within that region (0-based)
- Dash separates nesting levels

This makes the DOM structure self-documenting and enables precise CSS targeting:

```css
/* Target all right sidebars at any nesting level */
[id$="-R-0"] { /* ... */ }

/* Target deeply nested content areas */
[id^="C-"][id*="-C-"] { /* ... */ }

/* Target second element in any header */
[id^="H-1"] { /* ... */ }
```

## Layout Variants

### Two-Column Layout

```blade
<x-hlcrf::layout variant="two-column">
    <x-hlcrf::left>
        Navigation
    </x-hlcrf::left>

    <x-hlcrf::content>
        Main Content
    </x-hlcrf::content>
</x-hlcrf::layout>
```

### Three-Column Layout

```blade
<x-hlcrf::layout variant="three-column">
    <x-hlcrf::left>
        Left Sidebar
    </x-hlcrf::left>

    <x-hlcrf::content>
        Main Content
    </x-hlcrf::content>

    <x-hlcrf::right>
        Right Sidebar
    </x-hlcrf::right>
</x-hlcrf::layout>
```

### Full-Width Layout

```blade
<x-hlcrf::layout variant="full-width">
    <x-hlcrf::header>
        Header
    </x-hlcrf::header>

    <x-hlcrf::content>
        Full-Width Content
    </x-hlcrf::content>
</x-hlcrf::layout>
```

### Modal Layout

```blade
<x-hlcrf::layout variant="modal">
    <x-hlcrf::header>
        <h2>Edit Post</h2>
    </x-hlcrf::header>

    <x-hlcrf::content>
        <form>...</form>
    </x-hlcrf::content>

    <x-hlcrf::footer>
        <x-button type="submit">Save</x-button>
        <x-button variant="secondary" @click="close">Cancel</x-button>
    </x-hlcrf::footer>
</x-hlcrf::layout>
```

## Responsive Behavior

HLCRF layouts adapt to screen size:

```blade
<x-hlcrf::layout
    :breakpoints="[
        'mobile' => 'stack',      // Stack regions on mobile
        'tablet' => 'two-column', // Two columns on tablet
        'desktop' => 'three-column', // Three columns on desktop
    ]"
>
    <x-hlcrf::left>Sidebar</x-hlcrf::left>
    <x-hlcrf::content>Content</x-hlcrf::content>
    <x-hlcrf::right>Widgets</x-hlcrf::right>
</x-hlcrf::layout>
```

**Result:**
- **Mobile:** Left → Content → Right (stacked vertically)
- **Tablet:** Left | Content (side-by-side)
- **Desktop:** Left | Content | Right (three columns)

## Region Options

### Collapsible Regions

```blade
<x-hlcrf::left collapsible="true" collapsed="false">
    Navigation Menu
</x-hlcrf::left>
```

### Fixed Regions

```blade
<x-hlcrf::header fixed="true">
    Sticky Header
</x-hlcrf::header>
```

### Scrollable Regions

```blade
<x-hlcrf::content scrollable="true" max-height="600px">
    Long Content
</x-hlcrf::content>
```

### Region Width

```blade
<x-hlcrf::left width="250px">
    Fixed width sidebar
</x-hlcrf::left>

<x-hlcrf::right width="25%">
    Percentage width sidebar
</x-hlcrf::right>
```

## Conditional Regions

### Show/Hide Based on Conditions

```blade
<x-hlcrf::layout>
    @auth
        <x-hlcrf::header>
            <x-user-nav />
        </x-hlcrf::header>
    @endauth

    <x-hlcrf::content>
        Main Content
    </x-hlcrf::content>

    @can('view-admin-sidebar')
        <x-hlcrf::right>
            <x-admin-widgets />
        </x-hlcrf::right>
    @endcan
</x-hlcrf::layout>
```

### Feature Flags

```blade
<x-hlcrf::layout>
    <x-hlcrf::content>
        Content
    </x-hlcrf::content>

    @feature('advanced-analytics')
        <x-hlcrf::right>
            <x-analytics-widgets />
        </x-hlcrf::right>
    @endfeature
</x-hlcrf::layout>
```

## Styling

### Custom Classes

```blade
<x-hlcrf::layout class="min-h-screen bg-gray-50">
    <x-hlcrf::header class="bg-white shadow">
        Header
    </x-hlcrf::header>

    <x-hlcrf::content class="max-w-7xl mx-auto py-6">
        Content
    </x-hlcrf::content>
</x-hlcrf::layout>
```

### Slot Attributes

```blade
<x-hlcrf::left
    class="bg-gray-900 text-white"
    width="256px"
>
    Dark Sidebar
</x-hlcrf::left>
```

## Real-World Examples

### Marketing Landing Page

```blade
<x-hlcrf::layout>
    {{-- Sticky header with CTA --}}
    <x-hlcrf::header fixed="true">
        <nav>
            <x-logo />
            <x-nav-links />
            <x-cta-button>Get Started</x-cta-button>
        </nav>
    </x-hlcrf::header>

    {{-- Hero section with sidebar --}}
    <x-hlcrf::content>
        <x-hlcrf::layout>
            <x-hlcrf::content>
                <x-hero-section />
            </x-hlcrf::content>

            <x-hlcrf::right>
                <x-trust-badges />
                <x-testimonial />
            </x-hlcrf::right>
        </x-hlcrf::layout>
    </x-hlcrf::content>

    {{-- Footer with newsletter --}}
    <x-hlcrf::footer>
        <x-hlcrf::layout>
            <x-hlcrf::left>
                <x-footer-nav />
            </x-hlcrf::left>

            <x-hlcrf::content>
                <x-newsletter-signup />
            </x-hlcrf::content>
        </x-hlcrf::layout>
    </x-hlcrf::footer>
</x-hlcrf::layout>
```

### E-Commerce Product Page

```blade
<x-hlcrf::layout>
    <x-hlcrf::header>
        <x-store-header />
    </x-hlcrf::header>

    <x-hlcrf::content>
        <x-hlcrf::layout>
            {{-- Product images --}}
            <x-hlcrf::left width="60%">
                <x-product-gallery :images="$product->images" />
            </x-hlcrf::left>

            {{-- Product details and buy box --}}
            <x-hlcrf::right width="40%">
                <x-product-info :product="$product" />
                <x-buy-box :product="$product" />
                <x-delivery-info />
            </x-hlcrf::right>
        </x-hlcrf::layout>

        {{-- Reviews and recommendations --}}
        <x-hlcrf::layout>
            <x-hlcrf::content>
                <x-product-reviews :product="$product" />
            </x-hlcrf::content>

            <x-hlcrf::right>
                <x-recommended-products :product="$product" />
            </x-hlcrf::right>
        </x-hlcrf::layout>
    </x-hlcrf::content>
</x-hlcrf::layout>
```

### Blog with Ads

```blade
<x-hlcrf::layout>
    <x-hlcrf::header>
        <x-blog-header />
    </x-hlcrf::header>

    <x-hlcrf::content>
        <x-hlcrf::layout>
            {{-- Sidebar navigation --}}
            <x-hlcrf::left width="250px">
                <x-category-nav />
                <x-ad-slot position="sidebar-top" />
            </x-hlcrf::left>

            {{-- Article content --}}
            <x-hlcrf::content>
                <article>
                    <h1>{{ $post->title }}</h1>
                    <x-ad-slot position="article-top" />
                    {!! $post->content !!}
                    <x-ad-slot position="article-bottom" />
                </article>

                <x-comments :post="$post" />
            </x-hlcrf::content>

            {{-- Widgets and ads --}}
            <x-hlcrf::right width="300px">
                <x-ad-slot position="sidebar-right-1" />
                <x-popular-posts />
                <x-ad-slot position="sidebar-right-2" />
                <x-newsletter-widget />
            </x-hlcrf::right>
        </x-hlcrf::layout>
    </x-hlcrf::content>

    <x-hlcrf::footer>
        <x-blog-footer />
    </x-hlcrf::footer>
</x-hlcrf::layout>
```

## Advanced Patterns

### Dynamic Region Loading

```blade
<x-hlcrf::layout>
    <x-hlcrf::content>
        Main Content
    </x-hlcrf::content>

    <x-hlcrf::right>
        {{-- Load widgets based on page --}}
        @foreach($widgets as $widget)
            @include("widgets.{$widget}")
        @endforeach
    </x-hlcrf::right>
</x-hlcrf::layout>
```

### Livewire Integration

```blade
<x-hlcrf::layout>
    <x-hlcrf::header>
        @livewire('global-search')
    </x-hlcrf::header>

    <x-hlcrf::content>
        @livewire('post-list')
    </x-hlcrf::content>

    <x-hlcrf::right>
        @livewire('post-filters')
    </x-hlcrf::right>
</x-hlcrf::layout>
```

### Portal Teleportation

Send content to regions from anywhere:

```blade
{{-- Page content --}}
<x-hlcrf::content>
    <h1>My Page</h1>

    {{-- Component that teleports to header --}}
    <x-page-actions>
        <x-button>Action 1</x-button>
        <x-button>Action 2</x-button>
    </x-page-actions>
</x-hlcrf::content>

{{-- page-actions.blade.php component --}}
<x-hlcrf::portal target="header-actions">
    {{ $slot }}
</x-hlcrf::portal>
```

## Implementation

### Layout Component

```php
<?php

namespace Core\Front\Components\View\Components;

use Illuminate\View\Component;

class HlcrfLayout extends Component
{
    public function __construct(
        public ?string $variant = 'three-column',
        public array $breakpoints = [],
    ) {}

    public function render()
    {
        return view('components.hlcrf.layout');
    }
}
```

### Layout View

```blade
{{-- components/hlcrf/layout.blade.php --}}
<div class="hlcrf-layout hlcrf-variant-{{ $variant }}">
    @if($header ?? false)
        <div class="hlcrf-region hlcrf-header">
            {{ $header }}
        </div>
    @endif

    <div class="hlcrf-main">
        @if($left ?? false)
            <div class="hlcrf-region hlcrf-left">
                {{ $left }}
            </div>
        @endif

        <div class="hlcrf-region hlcrf-content">
            {{ $content ?? $slot }}
        </div>

        @if($right ?? false)
            <div class="hlcrf-region hlcrf-right">
                {{ $right }}
            </div>
        @endif
    </div>

    @if($footer ?? false)
        <div class="hlcrf-region hlcrf-footer">
            {{ $footer }}
        </div>
    @endif
</div>
```

## Testing

### Component Testing

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class HlcrfLayoutTest extends TestCase
{
    public function test_renders_three_column_layout(): void
    {
        $view = $this->blade(
            '<x-hlcrf::layout>
                <x-hlcrf::left>Left</x-hlcrf::left>
                <x-hlcrf::content>Content</x-hlcrf::content>
                <x-hlcrf::right>Right</x-hlcrf::right>
            </x-hlcrf::layout>'
        );

        $view->assertSee('Left');
        $view->assertSee('Content');
        $view->assertSee('Right');
    }

    public function test_optional_regions(): void
    {
        $view = $this->blade(
            '<x-hlcrf::layout>
                <x-hlcrf::content>Content Only</x-hlcrf::content>
            </x-hlcrf::layout>'
        );

        $view->assertSee('Content Only');
        $view->assertDontSee('hlcrf-left');
        $view->assertDontSee('hlcrf-right');
    }
}
```

## Best Practices

### 1. Use Semantic Regions

```blade
{{-- ✅ Good - semantic use --}}
<x-hlcrf::header>Global Navigation</x-hlcrf::header>
<x-hlcrf::left>Page Navigation</x-hlcrf::left>
<x-hlcrf::content>Main Content</x-hlcrf::content>
<x-hlcrf::right>Contextual Help</x-hlcrf::right>

{{-- ❌ Bad - misuse of regions --}}
<x-hlcrf::header>Sidebar Content</x-hlcrf::header>
<x-hlcrf::left>Footer Content</x-hlcrf::left>
```

### 2. Keep Regions Optional

```blade
{{-- ✅ Good - gracefully handles missing regions --}}
<x-hlcrf::layout>
    <x-hlcrf::content>
        Content works without sidebars
    </x-hlcrf::content>
</x-hlcrf::layout>
```

### 3. Consistent Widths

```blade
{{-- ✅ Good - consistent sidebar widths --}}
<x-hlcrf::left width="256px">Nav</x-hlcrf::left>
<x-hlcrf::right width="256px">Widgets</x-hlcrf::right>
```

### 4. Mobile-First

```blade
{{-- ✅ Good - stack on mobile --}}
<x-hlcrf::layout
    :breakpoints="['mobile' => 'stack', 'desktop' => 'three-column']"
>
```

## Learn More

- [Admin Components](/packages/admin#components)
- [Livewire Integration](/packages/admin#livewire)
- [Responsive Design](/patterns-guide/responsive-design)
