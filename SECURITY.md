# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 1.x     | :white_check_mark: |
| < 1.0   | :x:                |

## Reporting a Vulnerability

**Please do not report security vulnerabilities through public GitHub issues.**

Instead, please report them via email to: **support@host.uk.com**

You should receive a response within 48 hours. If for some reason you do not, please follow up via email to ensure we received your original message.

## What to Include

Please include the following information in your report:

- **Type of vulnerability** (e.g., SQL injection, XSS, authentication bypass)
- **Full paths** of source file(s) related to the vulnerability
- **Location** of the affected source code (tag/branch/commit or direct URL)
- **Step-by-step instructions** to reproduce the issue
- **Proof-of-concept or exploit code** (if possible)
- **Impact** of the vulnerability and how an attacker might exploit it

This information will help us triage your report more quickly.

## Response Process

1. **Acknowledgment** - We'll confirm receipt of your vulnerability report within 48 hours
2. **Assessment** - We'll assess the vulnerability and determine its severity (typically within 5 business days)
3. **Fix Development** - We'll develop a fix for the vulnerability
4. **Disclosure** - Once a fix is available, we'll:
   - Release a security patch
   - Publish a security advisory
   - Credit the reporter (unless you prefer to remain anonymous)

## Security Update Policy

Security updates are released as soon as possible after a vulnerability is confirmed and patched. We follow these severity levels:

### Critical
- **Response time:** Within 24 hours
- **Patch release:** Within 48 hours
- **Examples:** Remote code execution, SQL injection, authentication bypass

### High
- **Response time:** Within 48 hours
- **Patch release:** Within 5 business days
- **Examples:** Privilege escalation, XSS, CSRF

### Medium
- **Response time:** Within 5 business days
- **Patch release:** Next scheduled release
- **Examples:** Information disclosure, weak cryptography

### Low
- **Response time:** Within 10 business days
- **Patch release:** Next scheduled release
- **Examples:** Minor security improvements

## Security Features

The Core PHP Framework includes several security features:

### Multi-Tenant Isolation
- Automatic workspace scoping prevents cross-tenant data access
- Strict mode throws exceptions on missing workspace context
- Request validation ensures workspace context authenticity

### API Security
- Bcrypt hashing for API keys (SHA-256 legacy support)
- Rate limiting per workspace with burst allowance
- HMAC-SHA256 webhook signing
- Scope-based permissions

### SQL Injection Prevention
- Multi-layer query validation (MCP package)
- Blocked keywords (INSERT, UPDATE, DELETE, DROP)
- Pattern detection for SQL injection attempts
- Read-only database connection support
- Table access controls

### Input Sanitization
- Built-in HTML/JS sanitization
- XSS prevention
- Email validation and disposable email blocking

### Security Headers
- Content Security Policy (CSP)
- HSTS, X-Frame-Options, X-Content-Type-Options
- Referrer Policy
- Permissions Policy

### Action Gate System
- Request whitelisting for sensitive operations
- Training mode for development
- Audit logging for all actions

## Security Best Practices

When using the Core PHP Framework:

### API Keys
- Store API keys securely (never in version control)
- Use environment variables or secure key management
- Rotate keys regularly
- Use minimal required scopes

### Database Access
- Use read-only connections for MCP tools
- Configure blocked tables for sensitive data
- Enable query whitelisting in production

### Workspace Context
- Always validate workspace context in custom tools
- Use `RequiresWorkspaceContext` trait
- Never bypass workspace scoping

### Rate Limiting
- Configure appropriate limits per tier
- Monitor rate limit violations
- Implement backoff strategies in API clients

### Activity Logging
- Enable activity logging for sensitive operations
- Regularly review activity logs
- Set appropriate retention periods

## Security Changelog

See [packages/core-mcp/changelog/2026/jan/security.md](packages/core-mcp/changelog/2026/jan/security.md) for recent security fixes.

## Credits

We appreciate the security research community and would like to thank the following researchers for responsibly disclosing vulnerabilities:

- *No vulnerabilities reported yet*

## Bug Bounty Program

We do not currently have a formal bug bounty program, but we deeply appreciate security research. Researchers who report valid security vulnerabilities will be:

- Credited in our security advisories (if desired)
- Listed in this document
- Given early access to security patches

## PGP Key

For sensitive security reports, you may encrypt your message using our PGP key:

```
-----BEGIN PGP PUBLIC KEY BLOCK-----
[To be added if needed]
-----END PGP PUBLIC KEY BLOCK-----
```

## Contact

- **Security Email:** support@host.uk.com
- **General Support:** https://github.com/host-uk/core-php/discussions
- **GitHub Security Advisories:** https://github.com/host-uk/core-php/security/advisories

## Disclosure Policy

When working with us according to this policy, you can expect us to:

- Respond to your report promptly
- Keep you informed about our progress
- Treat your report confidentially
- Credit your discovery publicly (if desired)
- Work with you to fully understand and resolve the issue

We request that you:

- Give us reasonable time to fix the vulnerability before public disclosure
- Make a good faith effort to avoid privacy violations, data destruction, and service disruption
- Do not access or modify data that doesn't belong to you
- Do not perform attacks that could harm reliability or integrity of our services
