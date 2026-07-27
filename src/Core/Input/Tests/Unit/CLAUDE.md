# Input/Tests/Unit/ — Input Filtering Tests

## Test Files

| File | Purpose |
|------|---------|
| `InputFilteringTest.php` | Pest unit tests for the `Sanitiser` class. Covers clean input passthrough (ASCII, punctuation, UTF-8), XSS prevention, SQL injection stripping, null byte removal, and edge cases. |

Tests the WAF layer that `Core\Init` uses to sanitise `$_GET` and `$_POST` before Laravel processes the request.
