---
title: Security
description: Security considerations and audit notes for core-developer
updated: 2026-01-29
---

# Security Considerations

The `core-developer` package provides powerful administrative capabilities that require careful security controls. This document outlines the security model, known risks, and mitigation strategies.

## Threat Model

### Assets Protected

1. **Application logs** - May contain tokens, passwords, PII in error messages
2. **Database access** - Read-only query execution against production data
3. **SSH keys** - Encrypted private keys for server connections
4. **Cache data** - Application cache, session data, config cache
5. **Route information** - Full application route structure

### Threat Actors

1. **Unauthorized users** - Non-Hades users attempting to access dev tools
2. **Compromised Hades account** - Attacker with valid Hades credentials
3. **SSRF/Injection** - Attacker manipulating dev tools to access internal resources
4. **Data exfiltration** - Extracting sensitive data via dev tools

## Authorization Model

### Hades Tier Requirement

All developer tools require "Hades" access, verified via the `isHades()` method on the User model. This is enforced at multiple layers:

| Layer | Implementation | File |
|-------|----------------|------|
| Middleware | `RequireHades::handle()` | `src/Middleware/RequireHades.php` |
| Component | `checkHadesAccess()` in `mount()` | All Livewire components |
| API | Controller `authorize()` calls | `src/Controllers/DevController.php` |
| Menu | `admin` flag filtering | `src/Boot.php` |

### Defence in Depth

The authorization is intentionally redundant:
- API routes use `RequireHades` middleware
- Livewire components check in `mount()`
- Some controller methods call `$this->authorize()`

This ensures access is blocked even if one layer fails.

### Known Issue: Test Environment

Tests currently pass without setting Hades tier on the test user. This suggests authorization may not be properly enforced in the test environment. See TODO.md for remediation.

## Data Protection

### Log Redaction

The `LogReaderService` automatically redacts sensitive patterns before displaying logs:

| Pattern | Replacement |
|---------|-------------|
| Stripe API keys | `[STRIPE_KEY_REDACTED]` |
| GitHub tokens | `[GITHUB_TOKEN_REDACTED]` |
| Bearer tokens | `Bearer [TOKEN_REDACTED]` |
| API keys/secrets | `[KEY_REDACTED]` / `[REDACTED]` |
| AWS credentials | `[AWS_KEY_REDACTED]` / `[AWS_SECRET_REDACTED]` |
| Database URLs | Connection strings with `[USER]:[PASS]` |
| Email addresses | Partial: `jo***@example.com` |
| IP addresses | Partial: `192.168.xxx.xxx` |
| Credit card numbers | `[CARD_REDACTED]` |
| JWT tokens | `[JWT_REDACTED]` |
| Private keys | `[PRIVATE_KEY_REDACTED]` |

**Limitation**: Patterns are regex-based and may not catch all sensitive data. Custom application secrets with non-standard formats will not be redacted.

### SSH Key Storage

Server private keys are:
- Encrypted at rest using Laravel's `encrypted` cast
- Hidden from serialization (`$hidden` array)
- Never exposed in API responses or views
- Stored in `text` column (supports long keys)

### Database Query Tool

The database query component restricts access to read-only operations:

```php
protected const ALLOWED_STATEMENTS = ['SELECT', 'SHOW', 'DESCRIBE', 'EXPLAIN'];
```

**Known Risk**: The current implementation only checks the first word, which does not prevent:
- Stacked queries: `SELECT 1; DROP TABLE users`
- Subqueries with side effects (MySQL stored procedures)

**Mitigation**: Use a proper SQL parser or prevent semicolons entirely.

### Session Data Exposure

The `/hub/api/dev/session` endpoint exposes:
- Session ID
- User IP address
- User agent (truncated to 100 chars)
- Request method and URL

This is intentional for debugging but could be abused for session hijacking if credentials are compromised.

## Rate Limiting

All API endpoints have rate limits to prevent abuse:

| Endpoint | Limit | Rationale |
|----------|-------|-----------|
| Cache clear | 10/min | Prevent DoS via rapid cache invalidation |
| Log reading | 30/min | Limit log scraping |
| Route listing | 30/min | Prevent enumeration attacks |
| Session info | 60/min | Higher limit for debugging workflows |

Rate limits are per-user (authenticated) or per-IP (unauthenticated).

## SSH Connection Security

### Key Handling

The `testConnection()` method in `Servers.php` creates a temporary key file:

```php
$tempKeyPath = sys_get_temp_dir().'/ssh_test_'.uniqid();
file_put_contents($tempKeyPath, $server->getDecryptedPrivateKey());
chmod($tempKeyPath, 0600);
```

**Risk**: Predictable filename pattern and race condition window between write and use.

**Recommendation**: Use `tempnam()` for unique filename, write with restrictive umask.

### Connection Validation

- `StrictHostKeyChecking=no` is used for convenience but prevents MITM detection
- `BatchMode=yes` prevents interactive prompts
- `ConnectTimeout=10` limits hanging connections

### Workspace Isolation

The `RemoteServerManager::connect()` method validates workspace ownership before connecting:

```php
if (! $server->belongsToCurrentWorkspace()) {
    throw new SshConnectionException('Unauthorised access to server.', $server->name);
}
```

This prevents cross-tenant server access.

## Route Testing Security

### Environment Restriction

Route testing is only available in `local` and `testing` environments:

```php
public function isTestingAllowed(): bool
{
    return App::environment(['local', 'testing']);
}
```

This prevents accidental data modification in production.

### Destructive Operation Warnings

Routes using `DELETE`, `PUT`, `PATCH`, `POST` methods are marked as destructive and show warnings in the UI.

### CSRF Consideration

Test requests bypass CSRF as they are internal requests. The `X-Requested-With: XMLHttpRequest` header is set by default.

## Cookie Security

### Hades Cookie

The `SetHadesCookie` listener sets a cookie on login:

| Attribute | Value | Purpose |
|-----------|-------|---------|
| Value | Encrypted token | Validates Hades status |
| Duration | 1 year | Long-lived for convenience |
| HttpOnly | true | Prevents XSS access |
| Secure | true (production) | HTTPS only in production |
| SameSite | lax | CSRF protection |

### Icon Settings Cookie

`ApplyIconSettings` middleware reads `icon-style` and `icon-size` cookies set by JavaScript. These are stored in session for Blade component access.

**Risk**: Cookie values are user-controlled. Ensure they are properly escaped in views.

## Audit Logging

### Logged Actions

| Action | What's Logged |
|--------|---------------|
| Log clear | user_id, email, previous_size_bytes, IP |
| Database query | user_id, email, query, row_count, execution_time, IP |
| Blocked query | user_id, email, query (attempted), IP |
| Route test | user_id, route, method, IP |
| Server failure | Server ID, failure reason (via activity log) |

### Activity Log

Server model uses Spatie ActivityLog for tracking changes:
- Logged fields: name, ip, port, user, status
- Only dirty attributes logged
- Empty logs suppressed

## Third-Party Security

### Telescope

- Sensitive headers hidden: `cookie`, `x-csrf-token`, `x-xsrf-token`
- Sensitive parameters hidden: `_token`
- Gate restricts to Hades users (production) or all users (local)

### Horizon

- Gate restricts to Hades users
- Notifications configured via config (not hardcoded emails)

## Security Checklist for New Features

When adding new developer tools:

- [ ] Enforce Hades authorization in middleware AND component
- [ ] Add rate limiting for API endpoints
- [ ] Redact sensitive data in output
- [ ] Audit destructive operations
- [ ] Restrict environment (local/testing) for dangerous features
- [ ] Validate and sanitize all user input
- [ ] Use prepared statements for database queries
- [ ] Clean up temporary files/resources
- [ ] Document security considerations

## Incident Response

### If Hades credentials are compromised:

1. Revoke the user's Hades access
2. Rotate `HADES_TOKEN` environment variable
3. Review audit logs for suspicious activity
4. Check server access logs for SSH activity
5. Consider rotating SSH keys for connected servers

### If SSH key is exposed:

1. Delete the server record immediately
2. Regenerate SSH key on the actual server
3. Review server logs for unauthorized access
4. Update the server record with new key

## Recommendations for Production

1. **Separate Hades token per environment** - Don't use same token across staging/production
2. **Regular audit log review** - Monitor for unusual access patterns
3. **Limit Hades users** - Only grant to essential personnel
4. **Use hardware keys** - For servers, prefer hardware security modules
5. **Network segmentation** - Restrict admin panel to internal networks
6. **Two-factor authentication** - Require 2FA for Hades-tier accounts
7. **Session timeout** - Consider shorter session duration for Hades users
