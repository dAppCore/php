# Headers/Testing/ — Security Header Test Utilities

## Files

| File | Purpose |
|------|---------|
| `HeaderAssertions` | Trait with assertion methods for testing security headers in HTTP responses. Provides `assertHasSecurityHeaders()`, `assertCspContains()`, etc. |
| `SecurityHeaderTester` | Standalone static helper for validating security headers. Can be used without traits for flexible testing scenarios. |

Used by feature tests (`SecurityHeadersTest`) to verify CSP, HSTS, X-Frame-Options, and other security headers.
