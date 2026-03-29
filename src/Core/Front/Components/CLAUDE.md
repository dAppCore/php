# Front/Components

Core UI component system. Provides `<core:xyz>` Blade tag syntax and data-driven PHP component builders for programmatic UI composition.

## Files

- **Boot.php** -- ServiceProvider registering multiple Blade namespaces: `core::` (core components + `<core:xyz>` tags), `layouts::` (Livewire layout resolution), `front::` (front-end satellite components), `errors::` (error pages). Adds blade view paths for Livewire's `->layout()` resolution.
- **CoreTagCompiler.php** -- Blade precompiler for `<core:xyz>` tag syntax. Compiles to `core::` anonymous components.
- **Component.php** -- Abstract base for data-driven UI components. Fluent interface with `attr()`, `class()`, `id()`, `buildAttributes()`. Implements `Htmlable`. Used by MCP tools and agents to compose UIs without Blade templates.
- **Button.php** -- Button builder. Variants: primary, secondary, danger, ghost. Sizes: sm, md, lg. Supports link buttons (`href()`), disabled state, submit type.
- **Card.php** -- Card builder with title, description, body content, and action buttons in footer.
- **Heading.php** -- Heading builder (h1-h6) with optional description subtitle. Size classes auto-mapped from level.
- **Layout.php** -- HLCRF Layout Compositor. Data-driven layout builder where H=Header, L=Left, C=Content, R=Right, F=Footer. Variant string defines which slots exist (e.g., `'HLCF'`, `'HCF'`, `'HC'`). Supports nesting and hierarchical path tracking.
- **NavList.php** -- Navigation list builder with heading, items (label + href + icon + active), and dividers.
- **Text.php** -- Text builder. Tags: span, p, div. Variants: default, muted, success, warning, error.

## Tag Syntax

```blade
<core:icon name="star" />
<core:tabs>...</core:tabs>
```

## Programmatic Usage

```php
Layout::make('HLCF')
    ->h('<nav>Logo</nav>')
    ->l(NavList::make()->item('Dashboard', '/hub'))
    ->c(Card::make()->title('Settings')->body('Content'))
    ->f('<footer>Links</footer>');
```
