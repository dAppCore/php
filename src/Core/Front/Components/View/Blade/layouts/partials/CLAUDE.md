# Blade/layouts/partials

Shared layout fragments included by the layout templates.

## Files

- **base.blade.php** -- Base HTML document shell. Handles `<!DOCTYPE>`, `<html>`, `<head>`, OG meta tags, CSRF token, Vite assets, and optional particle animation canvas. All layout templates extend this.
  - Props: `title`, `description`, `ogImage`, `ogType`, `particles` (bool)
- **fonts.blade.php** -- External font loading (Google Fonts link tags)
- **fonts-inline.blade.php** -- Inline font declarations (for critical rendering path)
- **footer.blade.php** -- Shared site footer with navigation links, social links, and copyright
- **header.blade.php** -- Shared site header/navigation bar with logo, menu items, and auth links
