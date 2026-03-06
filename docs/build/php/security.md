# Security Overview

Core PHP Framework is built with security as a foundational principle. This guide covers the security features, best practices, and considerations for building secure applications.

## Security Features

### Multi-Tenant Isolation

Complete data isolation between workspaces and namespaces:

```php
// Workspace-scoped models
class Post extends Model
{
    use BelongsToWorkspace; // Automatic workspace isolation
}

// Namespace-scoped models
class Page extends Model
{
    use BelongsToNamespace; // Automatic namespace isolation
}
```

**Protection:**
- Automatic query scoping
- Workspace context validation
- Strict mode enforcement
- Cache isolation

[Learn more about Multi-Tenancy →](/architecture/multi-tenancy)
[Learn more about Namespaces →](/security/namespaces)

### API Security

#### Secure API Keys

API keys are hashed with bcrypt and never stored in plaintext:

```php
$apiKey = ApiKey::create([
    'name' => 'Mobile App',
    'workspace_id' => $workspace->id,
    'scopes' => ['posts:read', 'posts:write'],
]);

// Plaintext key only shown once!
$plaintext = $apiKey->plaintext_key; // sk_live_...

// Hash stored in database
// Verification uses bcrypt's secure comparison
```

**Features:**
- Bcrypt hashing
- Key rotation with grace period
- Scope-based permissions
- Rate limiting per key
- Usage tracking

#### Scope Enforcement

Fine-grained API permissions:

```php
// Middleware enforces scopes
Route::middleware('scope:posts:write')
    ->post('/posts', [PostController::class, 'store']);

// Check scopes in code
if (! $request->user()->tokenCan('posts:delete')) {
    abort(403, 'Insufficient permissions');
}
```

**Available Scopes:**
- `posts:read`, `posts:write`, `posts:delete`
- `categories:read`, `categories:write`
- `analytics:read`
- `webhooks:manage`
- `keys:manage`

#### Rate Limiting

Tier-based rate limiting prevents abuse:

```php
// config/core-api.php
'rate_limits' => [
    'tiers' => [
        'free' => ['requests' => 1000, 'window' => 60],
        'pro' => ['requests' => 10000, 'window' => 60],
        'enterprise' => ['requests' => null], // unlimited
    ],
],
```

**Response Headers:**
```
X-RateLimit-Limit: 10000
X-RateLimit-Remaining: 9995
X-RateLimit-Reset: 1640995200
```

#### Webhook Signatures

HMAC-SHA256 signatures prevent tampering:

```php
// Webhook payload signing
$signature = hash_hmac(
    'sha256',
    $timestamp . '.' . $payload,
    $webhookSecret
);

// Verification
if (! hash_equals($expected, $signature)) {
    abort(401, 'Invalid signature');
}

// Timestamp validation prevents replay attacks
if (abs(time() - $timestamp) > 300) {
    abort(401, 'Request too old');
}
```

[Learn more about API Security →](/packages/api)

### SQL Injection Prevention

Multi-layer protection for database queries:

```php
// config/core-mcp.php
'database' => [
    'validation' => [
        'enabled' => true,
        'blocked_keywords' => ['INSERT', 'UPDATE', 'DELETE', 'DROP'],
        'blocked_tables' => ['users', 'api_keys', 'password_resets'],
        'whitelist_enabled' => false,
    ],
],
```

**Validation Layers:**
1. **Keyword blocking** - Block dangerous SQL keywords
2. **Table restrictions** - Prevent access to sensitive tables
3. **Pattern detection** - Detect SQL injection patterns
4. **Whitelist validation** - Optional pre-approved queries
5. **Read-only connections** - Separate connection without write access

**Example:**

```php
class QueryDatabaseTool extends Tool
{
    public function handle(Request $request): Response
    {
        $query = $request->input('query');

        // Validates against all layers
        $this->validator->validate($query);

        // Execute on read-only connection
        $results = DB::connection('mcp_readonly')->select($query);

        return Response::success(['rows' => $results]);
    }
}
```

[Learn more about MCP Security →](/packages/mcp)

### Security Headers

Comprehensive security headers protect against common attacks:

```php
// config/core.php
'security_headers' => [
    'csp' => [
        'enabled' => true,
        'report_only' => false,
        'directives' => [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'nonce'"],
            'style-src' => ["'self'", "'unsafe-inline'"],
            'img-src' => ["'self'", 'data:', 'https:'],
            'connect-src' => ["'self'"],
            'font-src' => ["'self'", 'data:'],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'none'"],
        ],
    ],
    'hsts' => [
        'enabled' => true,
        'max_age' => 31536000, // 1 year
        'include_subdomains' => true,
        'preload' => true,
    ],
    'x_frame_options' => 'DENY',
    'x_content_type_options' => 'nosniff',
    'x_xss_protection' => '1; mode=block',
    'referrer_policy' => 'strict-origin-when-cross-origin',
],
```

**Protection Against:**
- **XSS** - Content Security Policy blocks inline scripts
- **Clickjacking** - X-Frame-Options prevents iframe embedding
- **MITM** - HSTS enforces HTTPS
- **Content Type Sniffing** - X-Content-Type-Options
- **Data Leakage** - Referrer Policy controls referrer info

**CSP Nonces:**

```blade
<script nonce="{{ csp_nonce() }}">
    // Inline script allowed via nonce
    console.log('Secure inline script');
</script>
```

### Input Validation & Sanitization

Comprehensive input handling:

```php
use Core\Input\Sanitiser;

$sanitiser = app(Sanitiser::class);

// Sanitize user input
$clean = $sanitiser->sanitize($userInput, [
    'strip_tags' => true,
    'trim' => true,
    'escape_html' => true,
]);

// Sanitize HTML content
$safeHtml = $sanitiser->sanitizeHtml($content, [
    'allowed_tags' => ['p', 'br', 'strong', 'em', 'a'],
    'allowed_attributes' => ['href', 'title'],
]);
```

**Features:**
- HTML tag stripping
- XSS prevention
- SQL injection prevention (via Eloquent)
- CSRF protection (Laravel default)
- Mass assignment protection

### Email Security

Disposable email detection and validation:

```php
use Core\Mail\EmailShield;

$shield = app(EmailShield::class);

$result = $shield->validate('user@tempmail.com');

if (! $result->isValid) {
    // Email failed validation
    // Reasons: disposable, syntax error, MX record invalid
    return back()->withErrors(['email' => $result->reason]);
}
```

**Checks:**
- Disposable email providers
- Syntax validation
- MX record verification
- Common typo detection
- Role-based email detection (abuse@, admin@, etc.)

### Authentication Security

#### Password Hashing

Laravel's bcrypt with automatic rehashing:

```php
// Hashing
$hashed = bcrypt('password');

// Verification with automatic rehash
if (Hash::check($password, $user->password)) {
    // Re-hash if using old cost
    if (Hash::needsRehash($user->password)) {
        $user->password = bcrypt($password);
        $user->save();
    }
}
```

#### Two-Factor Authentication

TOTP-based 2FA support:

```php
use Core\Mod\Tenant\Concerns\TwoFactorAuthenticatable;

class User extends Model
{
    use TwoFactorAuthenticatable;
}

// Enable 2FA
$secret = $user->enableTwoFactorAuth();
$qrCode = $user->getTwoFactorQrCode();

// Verify code
if ($user->verifyTwoFactorCode($code)) {
    // Code valid
}
```

#### Session Security

```php
// config/session.php
'secure' => env('SESSION_SECURE_COOKIE', true),
'http_only' => true,
'same_site' => 'lax',
'lifetime' => 120,
```

**Features:**
- Secure cookies (HTTPS only)
- HTTP-only cookies (no JavaScript access)
- SameSite protection
- Session regeneration on login
- Automatic logout on inactivity

### IP Blocklist

Automatic blocking of malicious IPs:

```php
use Core\Bouncer\BlocklistService;

$blocklist = app(BlocklistService::class);

// Check if IP is blocked
if ($blocklist->isBlocked($ip)) {
    abort(403, 'Access denied');
}

// Add IP to blocklist
$blocklist->block($ip, reason: 'Brute force attempt', duration: 3600);

// Remove from blocklist
$blocklist->unblock($ip);
```

**Features:**
- Temporary and permanent blocks
- Reason tracking
- Automatic expiry
- Admin interface
- Integration with rate limiting

### Action Gate

Request whitelisting for sensitive operations:

```php
use Core\Bouncer\Gate\Attributes\Action;

#[Action('post.publish', description: 'Publish a blog post')]
class PublishPost
{
    use Action;

    public function handle(Post $post): Post
    {
        $post->update(['published_at' => now()]);
        return $post;
    }
}
```

**Modes:**
- **Training Mode** - Log all requests without blocking
- **Enforcement Mode** - Block unauthorized requests
- **Audit Mode** - Log + alert on violations

**Configuration:**

```php
// config/core.php
'bouncer' => [
    'enabled' => true,
    'training_mode' => false,
    'block_unauthorized' => true,
    'log_all_requests' => true,
],
```

### Activity Logging

Comprehensive audit trail:

```php
use Core\Activity\Concerns\LogsActivity;

class Post extends Model
{
    use LogsActivity;

    protected array $activityLogAttributes = ['title', 'status', 'published_at'];
}

// Changes logged automatically
$post->update(['title' => 'New Title']);

// Retrieve activity
$activity = Activity::forSubject($post)
    ->latest()
    ->get();
```

**GDPR Compliance:**
- Optional IP address logging (disabled by default)
- Automatic anonymization after configurable period
- User data deletion on account closure
- Activity log pruning

[Learn more about Activity Logging →](/patterns-guide/activity-logging)

## Security Best Practices

### 1. Use Workspace/Namespace Scoping

Always scope data to workspaces or namespaces:

```php
// ✅ Good - automatic scoping
class Post extends Model
{
    use BelongsToWorkspace;
}

// ❌ Bad - no isolation
class Post extends Model { }
```

### 2. Validate All Input

Never trust user input:

```php
// ✅ Good - validation
$validated = $request->validate([
    'title' => 'required|max:255',
    'content' => 'required',
]);

// ❌ Bad - no validation
$post->update($request->all());
```

### 3. Use Parameterized Queries

Eloquent provides automatic protection:

```php
// ✅ Good - parameterized
Post::where('title', $title)->get();

// ❌ Bad - vulnerable to SQL injection
DB::select("SELECT * FROM posts WHERE title = '{$title}'");
```

### 4. Implement Rate Limiting

Protect all public endpoints:

```php
// ✅ Good - rate limited
Route::middleware('throttle:60,1')
    ->post('/api/posts', [PostController::class, 'store']);

// ❌ Bad - no rate limiting
Route::post('/api/posts', [PostController::class, 'store']);
```

### 5. Use HTTPS

Always enforce HTTPS in production:

```php
// app/Providers/AppServiceProvider.php
public function boot(): void
{
    if (app()->environment('production')) {
        URL::forceScheme('https');
    }
}
```

### 6. Implement Authorization

Use policies for authorization:

```php
// ✅ Good - policy check
$this->authorize('update', $post);

// ❌ Bad - no authorization
$post->update($request->validated());
```

### 7. Sanitize Output

Blade automatically escapes output:

```blade
{{-- ✅ Good - auto-escaped --}}
<p>{{ $post->title }}</p>

{{-- ❌ Bad - unescaped (only when needed) --}}
<div>{!! $post->content !!}</div>
```

### 8. Rotate Secrets

Regularly rotate secrets and API keys:

```php
// API key rotation
$newKey = $apiKey->rotate();

// Session secret rotation (in .env)
php artisan key:generate
```

### 9. Monitor Security Events

Log security-relevant events:

```php
activity()
    ->causedBy($user)
    ->performedOn($resource)
    ->withProperties(['ip' => $ip, 'user_agent' => $userAgent])
    ->log('unauthorized_access_attempt');
```

### 10. Keep Dependencies Updated

```bash
# Check for security updates
composer audit

# Update dependencies
composer update
```

## Reporting Security Vulnerabilities

If you discover a security vulnerability, please email:

**support@host.uk.com**

Do not create public GitHub issues for security vulnerabilities.

**Response Timeline:**
- **Critical**: 24 hours
- **High**: 48 hours
- **Medium**: 7 days
- **Low**: 14 days

[Full Disclosure Policy →](/security/responsible-disclosure)

## Security Checklist

Before deploying to production:

- [ ] HTTPS enforced
- [ ] Security headers configured
- [ ] Rate limiting enabled
- [ ] CSRF protection active
- [ ] Input validation implemented
- [ ] SQL injection protections verified
- [ ] XSS protections enabled
- [ ] Authentication secure (2FA optional)
- [ ] Authorization policies in place
- [ ] Activity logging enabled
- [ ] Error messages sanitized (no stack traces in production)
- [ ] Debug mode disabled (`APP_DEBUG=false`)
- [ ] Database credentials secured
- [ ] API keys rotated
- [ ] Backups configured
- [ ] Monitoring/alerting active

## Learn More

- [Namespaces & Entitlements →](/security/namespaces)
- [API Security →](/packages/api)
- [MCP Security →](/packages/mcp)
- [Multi-Tenancy →](/architecture/multi-tenancy)
- [Responsible Disclosure →](/security/responsible-disclosure)
