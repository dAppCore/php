# Blade/web

Public-facing page templates for workspace websites. Registered under the `web::` namespace.

## Files

- **home.blade.php** -- Workspace homepage template
- **page.blade.php** -- Generic content page template
- **waitlist.blade.php** -- Pre-launch waitlist/signup page template

These are rendered for workspace domains resolved by `FindDomainRecord` middleware. Used via `web::home`, `web::page`, `web::waitlist` view references.
