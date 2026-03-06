# API Authentication

Core PHP Framework provides multiple authentication methods for API access, including API keys, OAuth tokens, and session-based authentication.

## API Keys

API keys are the primary authentication method for external API access.

### Creating API Keys

```php
use Mod\Api\Models\ApiKey;

$apiKey = ApiKey::create([
    'name' => 'Mobile App',
    'workspace_id' => $workspace->id,
    'scopes' => ['posts:read', 'posts:write', 'categories:read'],
    'rate_limit_tier' => 'pro',
]);

// Get plaintext key (only shown once!)
$plaintext = $apiKey->plaintext_key; // sk_live_...
```

**Response:**
```json
{
  "id": 123,
  "name": "Mobile App",
  "key": "sk_live_abc123...",
  "scopes": ["posts:read", "posts:write"],
  "rate_limit_tier": "pro",
  "created_at": "2026-01-26T12:00:00Z"
}
```

::: warning
The plaintext API key is only shown once at creation. Store it securely!
:::

### Using API Keys

Include the API key in the `Authorization` header:

```bash
curl -H "Authorization: Bearer sk_live_abc123..." \
     https://api.example.com/v1/posts
```

Or use basic authentication:

```bash
curl -u sk_live_abc123: \
     https://api.example.com/v1/posts
```

### Key Format

API keys follow the format: `{prefix}_{environment}_{random}`

- **Prefix:** `sk` (secret key)
- **Environment:** `live` or `test`
- **Random:** 32 characters

**Examples:**
- `sk_live_`
- `sk_test_`

### Key Security

API keys are hashed with bcrypt before storage:

```php
// Creation
$hash = bcrypt($plaintext);

// Verification
if (Hash::check($providedKey, $apiKey->key_hash)) {
    // Valid key
}
```

**Security Features:**
- Never stored in plaintext
- Bcrypt hashing (cost factor: 10)
- Secure comparison with `hash_equals()`
- Rate limiting per key
- Automatic expiry support

### Key Rotation

Rotate keys regularly for security:

```php
$newKey = $apiKey->rotate();

// Returns new key object with:
// - New plaintext key
// - Same scopes and settings
// - Old key marked for deletion after grace period
```

**Grace Period:**
- Default: 24 hours
- Both old and new keys work during this period
- Old key auto-deleted after grace period

### Key Permissions

Control what each key can access:

```php
$apiKey = ApiKey::create([
    'name' => 'Read-Only Key',
    'scopes' => [
        'posts:read',
        'categories:read',
        'analytics:read',
    ],
]);
```

Available scopes documented in [Scopes & Permissions](#scopes--permissions).

## Sanctum Tokens

Laravel Sanctum provides token-based authentication for SPAs:

### Creating Tokens

```php
$user = User::find(1);

$token = $user->createToken('mobile-app', [
    'posts:read',
    'posts:write',
])->plainTextToken;
```

### Using Tokens

```bash
curl -H "Authorization: Bearer 1|abc123..." \
     https://api.example.com/v1/posts
```

### Token Abilities

Check token abilities in controllers:

```php
if ($request->user()->tokenCan('posts:write')) {
    // User has permission
}
```

## Session Authentication

For first-party applications, use session-based authentication:

```bash
# Login first
curl -X POST https://api.example.com/login \
     -H "Content-Type: application/json" \
     -d '{"email":"user@example.com","password":"secret"}' \
     -c cookies.txt

# Use session cookie
curl https://api.example.com/v1/posts \
     -b cookies.txt
```

## OAuth 2.0 (Optional)

If Laravel Passport is installed, OAuth 2.0 is available:

### Authorization Code Grant

```bash
# 1. Redirect user to authorization endpoint
https://api.example.com/oauth/authorize?
  client_id=CLIENT_ID&
  redirect_uri=CALLBACK_URL&
  response_type=code&
  scope=posts:read posts:write

# 2. Exchange code for token
curl -X POST https://api.example.com/oauth/token \
     -d "grant_type=authorization_code" \
     -d "client_id=CLIENT_ID" \
     -d "client_secret=CLIENT_SECRET" \
     -d "code=AUTH_CODE" \
     -d "redirect_uri=CALLBACK_URL"
```

### Client Credentials Grant

For server-to-server:

```bash
curl -X POST https://api.example.com/oauth/token \
     -d "grant_type=client_credentials" \
     -d "client_id=CLIENT_ID" \
     -d "client_secret=CLIENT_SECRET" \
     -d "scope=posts:read"
```

## Scopes & Permissions

### Available Scopes

| Scope | Description |
|-------|-------------|
| `posts:read` | Read blog posts |
| `posts:write` | Create and update posts |
| `posts:delete` | Delete posts |
| `categories:read` | Read categories |
| `categories:write` | Create and update categories |
| `analytics:read` | Access analytics data |
| `webhooks:manage` | Manage webhook endpoints |
| `keys:manage` | Manage API keys |
| `admin:*` | Full admin access |

### Scope Enforcement

Protect routes with scope middleware:

```php
Route::middleware('scope:posts:write')
    ->post('/posts', [PostController::class, 'store']);
```

### Wildcard Scopes

Use wildcards for broad permissions:

- `posts:*` - All post permissions
- `*:read` - Read access to all resources
- `*` - Full access (use sparingly!)

## Authentication Errors

### 401 Unauthorized

Missing or invalid credentials:

```json
{
  "message": "Unauthenticated."
}
```

**Causes:**
- No `Authorization` header
- Invalid API key
- Expired token
- Revoked credentials

### 403 Forbidden

Valid credentials but insufficient permissions:

```json
{
  "message": "This action is unauthorized.",
  "required_scope": "posts:write",
  "provided_scopes": ["posts:read"]
}
```

**Causes:**
- Missing required scope
- Workspace suspended
- Resource access denied

## Best Practices

### 1. Use Minimum Required Scopes

```php
// ✅ Good - specific scopes
$apiKey->scopes = ['posts:read', 'categories:read'];

// ❌ Bad - excessive permissions
$apiKey->scopes = ['*'];
```

### 2. Rotate Keys Regularly

```php
// Rotate every 90 days
if ($apiKey->created_at->diffInDays() > 90) {
    $apiKey->rotate();
}
```

### 3. Use Different Keys Per Client

```php
// ✅ Good - separate keys
ApiKey::create(['name' => 'Mobile App iOS']);
ApiKey::create(['name' => 'Mobile App Android']);

// ❌ Bad - shared key
ApiKey::create(['name' => 'All Mobile Apps']);
```

### 4. Monitor Key Usage

```php
$usage = ApiKey::find($id)->usage()
    ->whereBetween('created_at', [now()->subDays(7), now()])
    ->count();
```

### 5. Implement Key Expiry

```php
$apiKey = ApiKey::create([
    'name' => 'Temporary Key',
    'expires_at' => now()->addDays(30),
]);
```

## Rate Limiting

All authenticated requests are rate limited based on tier:

| Tier | Requests per Hour |
|------|------------------|
| Free | 1,000 |
| Pro | 10,000 |
| Enterprise | Unlimited |

Rate limit headers included in responses:

```
X-RateLimit-Limit: 10000
X-RateLimit-Remaining: 9995
X-RateLimit-Reset: 1640995200
```

## Testing Authentication

### Test Mode Keys

Use test keys for development:

```php
$testKey = ApiKey::create([
    'name' => 'Test Key',
    'environment' => 'test',
]);

// Key prefix: sk_test_...
```

Test keys:
- Don't affect production data
- Higher rate limits
- Clearly marked in admin panel
- Can be deleted without confirmation

### cURL Examples

**API Key:**
```bash
curl -H "Authorization: Bearer sk_live_..." \
     https://api.example.com/v1/posts
```

**Sanctum Token:**
```bash
curl -H "Authorization: Bearer 1|..." \
     https://api.example.com/v1/posts
```

**Session:**
```bash
curl -H "Cookie: laravel_session=..." \
     https://api.example.com/v1/posts
```

## Learn More

- [API Reference →](/api/endpoints)
- [Rate Limiting →](/api/endpoints#rate-limiting)
- [Error Handling →](/api/errors)
- [API Package →](/packages/api)
