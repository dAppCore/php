# HLCRF Compositor

**H**ierarchical **L**ayer **C**ompositing **R**ender **F**rame

A data-driven layout system where each composite contains up to five regions - Header, Left, Content, Right, Footer. Composites nest infinitely: any region can contain another composite.

## Quick Start

```php
use Core\Front\Components\Layout;

// Simple page layout
$page = Layout::make('HCF')
    ->h('<nav>Navigation</nav>')
    ->c('<article>Main content</article>')
    ->f('<footer>Footer</footer>');

echo $page;
```

## The Five Regions

| Letter | Region  | HTML Element | Purpose |
|--------|---------|--------------|---------|
| **H**  | Header  | `<header>`   | Top navigation, branding |
| **L**  | Left    | `<aside>`    | Left sidebar |
| **C**  | Content | `<main>`     | Primary content |
| **R**  | Right   | `<aside>`    | Right sidebar |
| **F**  | Footer  | `<footer>`   | Site footer |

## Variant Strings

The variant string defines which regions are active. What's missing defines the layout type.

| Variant | Description | Use Case |
|---------|-------------|----------|
| `C` | Content only | Widgets, embeds |
| `HCF` | Header, Content, Footer | Standard page |
| `HLCF` | + Left sidebar | Admin panel |
| `HLCRF` | All regions | Full dashboard |

```
HCF layout:
┌─────────────────────────┐
│           H             │
├─────────────────────────┤
│           C             │
├─────────────────────────┤
│           F             │
└─────────────────────────┘

HLCRF layout:
┌─────────────────────────┐
│           H             │
├───────┬─────────┬───────┤
│   L   │    C    │   R   │
├───────┴─────────┴───────┤
│           F             │
└─────────────────────────┘
```

## Nested Layouts

Any region can contain another layout. The path system tracks hierarchy:

```php
$sidebar = Layout::make('HCF')
    ->h('<h3>Widget</h3>')
    ->c('<ul>...</ul>')
    ->f('<a href="#">More</a>');

$page = Layout::make('HLCF')
    ->h(view('header'))
    ->l($sidebar)           // Nested layout
    ->c(view('content'))
    ->f(view('footer'));
```

The sidebar's regions receive paths: `L-H`, `L-C`, `L-F`.

## Inline Nesting Syntax

Declare nested structures in a single string using brackets:

```
H[LC]CF = Header contains a Left-Content layout, plus root Content and Footer

┌─────────────────────────────────┐
│ H ┌───────────┬───────────────┐ │
│   │    H-L    │      H-C      │ │
│   └───────────┴───────────────┘ │
├─────────────────────────────────┤
│               C                 │
├─────────────────────────────────┤
│               F                 │
└─────────────────────────────────┘
```

## Path-Based IDs

Every element has a unique, deterministic address:

```
L-H-0
│ │ └─ Block index (first block)
│ └─── Region in nested layout (Header)
└───── Region in root layout (Left)
```

Examples:
- `H-0` - First block in root Header
- `L-C-2` - Third block in Content of layout nested in Left
- `C-F-C-0` - First block in Content of layout nested in Footer of layout nested in Content

## API

### Factory

```php
Layout::make(string $variant = 'HCF', string $path = ''): static
```

### Slot Methods

```php
->h(mixed ...$items)  // Header
->l(mixed ...$items)  // Left
->c(mixed ...$items)  // Content
->r(mixed ...$items)  // Right
->f(mixed ...$items)  // Footer
```

Accepts: strings, `Htmlable`, `Renderable`, `View`, nested `Layout`, callables.

### Attributes

```php
->attributes(['id' => 'main'])
->class('my-layout')
```

### Rendering

```php
->render(): string
->toHtml(): string
(string) $layout
```

## Generated HTML

```html
<div class="hlcrf-layout" data-layout="root">
    <header class="hlcrf-header" data-slot="H">
        <div data-block="H-0">...</div>
    </header>
    <div class="hlcrf-body flex flex-1">
        <aside class="hlcrf-left shrink-0" data-slot="L">...</aside>
        <main class="hlcrf-content flex-1" data-slot="C">...</main>
        <aside class="hlcrf-right shrink-0" data-slot="R">...</aside>
    </div>
    <footer class="hlcrf-footer" data-slot="F">...</footer>
</div>
```

## CSS

Base styles for the grid structure:

```css
.hlcrf-layout {
    display: flex;
    flex-direction: column;
    min-height: 100%;
}

.hlcrf-body {
    display: flex;
    flex: 1;
}

.hlcrf-content {
    flex: 1;
}

.hlcrf-left,
.hlcrf-right {
    flex-shrink: 0;
}

/* Responsive: collapse sidebars on tablet */
@media (max-width: 1023px) {
    .hlcrf-left,
    .hlcrf-right {
        display: none;
    }
}
```

## Integration

### Livewire

```php
$layout = Layout::make('HLCF')
    ->h(livewire('nav'))
    ->l(livewire('sidebar'))
    ->c(livewire('content'))
    ->f(livewire('footer'));
```

### Blade

```blade
{!! Core\Front\Components\Layout::make('HCF')
    ->h(view('partials.header'))
    ->c($slot)
    ->f(view('partials.footer'))
!!}
```

### JSON Configuration

Store layout config in a JSON column:

```json
{
    "layout_type": {
        "desktop": "HLCRF",
        "tablet": "HCF",
        "phone": "CF"
    }
}
```

## Why HLCRF?

1. **Data-driven** - Layout is data, not templates
2. **Composable** - Infinite nesting with automatic path tracking
3. **Portable** - A string describes the entire structure
4. **Semantic** - Maps to HTML5 landmark elements
5. **Simple** - Five regions, predictable behaviour

---

*Location: `Core\Front\Components\Layout`*
