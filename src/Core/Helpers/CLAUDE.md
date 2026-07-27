# Core\Helpers

Shared utility classes registered as singletons. The `Boot` service provider also registers backward-compat aliases from the old `App\Support\*` namespace.

## Key Classes

| Class | Purpose |
|-------|---------|
| `RecoveryCode` | Generates 2FA recovery codes (`XXXXX-XXXXX` format) |
| `LoginRateLimiter` | Brute-force protection: 5 attempts / 60s per email+IP |
| `RateLimit` | Generic sliding-window rate limiter (cache-backed) |
| `PrivacyHelper` | GDPR IP anonymisation (truncation + daily-rotating SHA256 hashes) |
| `HadesEncrypt` | Hybrid AES-256-GCM + RSA encryption of exceptions for error pages |
| `File` | Base64-to-UploadedFile conversion, remote URL fetching, Content-Disposition parsing |
| `Cdn` | CDN asset URL generation with optional `cdn.{domain}` subdomain and file-hash cache busting |
| `UtmHelper` | UTM parameter extraction from Request, array, or URL string |
| `TimezoneList` | Timezone list generator with GMT offset formatting, grouped by continent |
| `HorizonStatus` | Laravel Horizon supervisor status check (inactive/paused/active) |
| `SystemLogs` | Reads `storage/logs/*.log` files, caps at 3 MB per file |
| `CommandResult` | Value object for remote command output (exitCode, output, error) |
| `ServiceCollection` | Typed collection of service provider classes with group/filter/metadata methods |
| `Log` | Social module logging facade, routes to configurable `social.log_channel` |

## Sub-directory

- `Rules/HexRule` -- Validation rule for hex colour codes (#fff or #ffffff). `forceFull` option rejects 3-digit codes.

## Patterns

- All classes are stateless or cache-backed -- no database models.
- `PrivacyHelper` has two anonymisation levels: standard (last octet) and strong (last 2 octets). IPv6 is fully handled.
- `HadesEncrypt` uses a hardcoded RSA public key (safe to commit) with env-var override. The HTML comment includes a whimsical "Hades" explanation for curious visitors.
- `Boot::registerBackwardCompatAliases()` uses `class_alias` to map old `App\Support\*` names -- remove these once the migration is complete.

## Integration

- `PrivacyHelper` is used by Analytics Center and BioHost for consistent IP handling.
- `RateLimit` cache keys are prefixed `social:` -- consider making this configurable.
- `Cdn` reads from `config('cdn.enabled')` and `config('core.cdn.subdomain')`.
