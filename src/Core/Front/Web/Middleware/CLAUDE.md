# Front/Web/Middleware

HTTP middleware for public web requests.

## Files

- **FindDomainRecord.php** -- Resolves the current workspace from the incoming domain. Sets `workspace_model` and `workspace` attributes on the request. Core domains (base domain, www, localhost) pass through. Checks custom domains first, then subdomain slugs. Requires `Core\Tenant` module.
- **RedirectIfAuthenticated.php** -- Redirects logged-in users away from guest-only pages (e.g., login). Redirects to `/` on the current domain instead of a global dashboard route.
- **ResilientSession.php** -- Catches session corruption (decryption errors, DB failures) and recovers gracefully by clearing cookies and retrying. Prevents 503 errors from APP_KEY changes or session table issues. Returns JSON for AJAX requests.
