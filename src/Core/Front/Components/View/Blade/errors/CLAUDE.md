# Blade/errors

HTTP error page templates. Registered under the `errors::` namespace.

## Files

- **404.blade.php** -- Not Found error page
- **500.blade.php** -- Internal Server Error page
- **503.blade.php** -- Service Unavailable / Maintenance Mode page

These override Laravel's default error views when the `errors::` namespace is registered.
