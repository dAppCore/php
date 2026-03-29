# Blade/components/satellite

Satellite site layout components for workspace-branded pages (e.g., bio links, landing pages).

## Files

- **footer-custom.blade.php** -- Custom footer for satellite sites with workspace branding, social links, custom links, contact info, and copyright. Supports configurable footer settings (show_default_links, position, custom_content).
- **layout.blade.php** -- Full HTML shell for satellite/workspace sites. Includes dark mode, meta tags, workspace branding, configurable footer. Used for public workspace pages served on custom domains or subdomains.

These are registered under the `front::` namespace via `<x-front::satellite.layout>`.
