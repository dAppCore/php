# Front/Admin/Blade/layouts

Layout templates for the admin panel.

## Files

- **app.blade.php** -- Full admin HTML shell with sidebar + content area layout. Includes dark mode (localStorage + cookie sync), FontAwesome Pro CSS, Vite assets (admin.css + app.js), Flux appearance/scripts, collapsible sidebar with `sidebarExpanded` Alpine state (persisted to localStorage), and light/dark mode toggle script.
  - Props: `title` (string, default 'Admin')
  - Slots: `$sidebar` (sidebar component), `$header` (top header), `$slot` (main content), `$head` (extra head content), `$scripts` (extra scripts)
  - Responsive: sidebar hidden on mobile, 20px collapsed / 64px expanded on desktop.
