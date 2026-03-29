# Headers

HTTP security headers, CSP nonce generation, device detection, and GeoIP lookup.

## What It Does

Four concerns bundled as a single module:

1. **SecurityHeaders** middleware -- Adds HSTS, CSP, Permissions-Policy, X-Frame-Options, X-Content-Type-Options, X-XSS-Protection, Referrer-Policy to all responses
2. **CspNonceService** -- Per-request cryptographic nonce for inline scripts/styles, integrated with Vite
3. **DetectDevice** -- User-Agent parsing for device type, OS, browser, in-app browser detection (14 platforms)
4. **DetectLocation** -- GeoIP from CloudFlare headers, custom headers, or MaxMind database

## Key Classes

| Class | Purpose |
|-------|---------|
| `Boot` | ServiceProvider: config, singletons, Blade directives (`@cspnonce`, `@cspnoncevalue`), `csp_nonce()` helper, Livewire `header-configuration-manager` component |
| `SecurityHeaders` | Middleware. CSP built from: base directives -> env overrides -> nonces -> CDN sources -> external services -> dev WebSocket -> report URI. Supports report-only mode |
| `CspNonceService` | Generates one nonce per request (128-bit, base64). Auto-registers with `Vite::useCspNonce()`. Methods: `getNonce()`, `getCspNonceDirective()`, `getNonceAttribute()` |
| `DetectDevice` | `parse($ua)` returns `{device_type, os_name, browser_name, in_app_browser, is_in_app}`. Helpers: `isBot()`, `isInstagram()`, `isFacebook()`, `isTikTok()`, `isMetaPlatform()`, `isStrictContentPlatform()` |
| `DetectLocation` | `lookup($ip, $request)` returns `{country_code, region, city}`. Checks CF headers first, then MaxMind DB. Cached 24h. Skips private IPs |
| `HeaderConfigurationManager` | Livewire component for admin-panel header config editing |

## Testing Support

| Class | Purpose |
|-------|---------|
| `HeaderAssertions` | Test assertion helpers for security headers |
| `SecurityHeaderTester` | Pre-built test scenarios |

## CSP Configuration

Config in `config/headers.php` (published via Boot):

```php
'csp' => [
    'enabled' => env('SECURITY_CSP_ENABLED', true),
    'report_only' => env('SECURITY_CSP_REPORT_ONLY', false),
    'nonce_enabled' => env('SECURITY_CSP_NONCE_ENABLED', true),
    'directives' => [...],
    'environment' => [
        'local' => ['script-src' => ["'unsafe-inline'", "'unsafe-eval'"]],
    ],
    'nonce_skip_environments' => ['local', 'development'],
    'external' => [
        'jsdelivr' => ['enabled' => env('SECURITY_CSP_JSDELIVR', false)],
        'google_analytics' => [...],
    ],
],
```

## Blade Usage

```blade
<script nonce="{{ csp_nonce() }}">...</script>
<script @cspnonce>...</script>
<script nonce="@cspnoncevalue">...</script>
```

## In-App Browser Detection

Detects 14 platforms: Instagram, Facebook, TikTok, Twitter/X, LinkedIn, Snapchat, Pinterest, Reddit, Threads, WeChat, LINE, Telegram, Discord, WhatsApp, plus generic WebView fallback.

Key distinction: `isStrictContentPlatform()` returns true for platforms that enforce content policies (useful for adult content warnings).

## Integration

- `SecurityHeaders` middleware is included in both `web` and `admin` middleware groups (configured in `Front/Web/Boot` and `Front/Admin/Boot`)
- Nonces auto-removed in `nonce_skip_environments` to not break HMR/dev tools
- HSTS only added in production
- Dev environments get WebSocket sources for Vite HMR (`localhost:8080`)
