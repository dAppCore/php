# Front/Web/Blade/components

Anonymous Blade components for the public web frontage. Used via `<web:xyz>` tag syntax.

## Components

- **nav-item.blade.php** -- Navigation list item with icon and active state highlighting.
  - Props: `href` (required), `icon` (string|null), `active` (bool)
  - Uses `<core:icon>` for icon rendering.

- **page.blade.php** -- Simple page wrapper for public web pages with optional title and description.
  - Props: `title` (string|null), `description` (string|null)
  - Provides max-width container with responsive padding.

## Usage

```blade
<web:page title="About Us">
    <p>Content here</p>
</web:page>
```
