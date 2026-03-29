# Front/Web/Blade/layouts

Layout templates for public web pages.

## Files

- **app.blade.php** -- Base HTML shell for public web pages. Includes dark mode support (cookie-based), CSRF meta tag, Vite assets (app.css + app.js), Flux appearance/scripts, and font loading via `layouts::partials.fonts`.
  - Props: `title` (string, defaults to `core.app.name` config)
  - Slots: `$head` (extra head content), `$scripts` (extra scripts before `</body>`)
  - Prevents white flash with inline critical CSS for dark mode.
