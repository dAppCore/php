# Bouncer/Database/Seeders/ — Bouncer Seeders

## Seeders

| File | Purpose |
|------|---------|
| `WebsiteRedirectSeeder.php` | Seeds 301 redirects for renamed website URLs. Uses the `RedirectService` to register old-to-new path mappings (e.g., `/services/biohost` -> `/services/bio`). Added during URL simplification (2026-01-16). |
