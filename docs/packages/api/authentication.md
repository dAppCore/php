# API Authentication

The API package provides secure authentication with bcrypt-hashed API keys, scope-based permissions, and automatic key rotation.

## API Key Management

### Creating Keys

```php
use Mod\Api\Models\ApiKey;

$apiKey = ApiKey::create([
    'name' => 'Mobile App Production',
    'workspace_id' => $workspace->id,
    'scopes' => ['posts:read', 'posts:write', 'categories:read'],
    'rate_limit_tier' => 'pro',
    'expires_at' => now()->addYear(),
]);

// Get plaintext key (only available once!)
$plaintext = $apiKey->plaintext_key;
// Returns: sk_live_abc123def456...
```

**Key Format:** `{prefix}_{environment}_{random}`
- Prefix: `sk` (secret key)
- Environment: `live` or `test`
- Random: 32-character string

### Secure Storage

Keys are hashed with bcrypt before storage:

```php
// Never stored in plaintext
$hash = bcrypt($plaintext);

// Stored in database
$apiKey->key_hash = $hash;

// Verification
if (Hash::check($providedKey, $apiKey->key_hash)) {
    // Valid key
}
```

### Key Rotation

Rotate keys with a grace period:

```php
$newKey = $apiKey->rotate([
    'grace_period_hours' => 24,
]);

// Returns new ApiKey with:
// - New plaintext key
// - Same scopes and settings
// - Old key marked for deletion after grace period
```

During the grace period, both keys work. After 24 hours, the old key is automatically deleted.

## Using API Keys

### Authorization Header

```bash
curl -H "Authorization: Bearer sk_live_abc123..." \
     https://api.example.com/v1/posts
```

### Basic Auth

```bash
curl -u sk_live_abc123: \
     https://api.example.com/v1/posts
```

### PHP Example

```php
use GuzzleHttp\Client;

$client = new Client([
    'base_uri' => 'https://api.example.com',
    'headers' => [
        'Authorization' => "Bearer {$apiKey}",
        'Accept' => 'application/json',
    ],
]);

$response = $client->get('/v1/posts');
```

### JavaScript Example

```javascript
const response = await fetch('https://api.example.com/v1/posts', {
  headers: {
    'Authorization': `Bearer ${apiKey}`,
    'Accept': 'application/json'
  }
});
```

## Scopes & Permissions

### Defining Scopes

```php
$apiKey = ApiKey::create([
    'scopes' => [
        'posts:read',      // Read posts
        'posts:write',     // Create/update posts
        'posts:delete',    // Delete posts
        'categories:read', // Read categories
    ],
]);
```

### Common Scopes

| Scope | Description |
|-------|-------------|
| `{resource}:read` | Read access |
| `{resource}:write` | Create and update |
| `{resource}:delete` | Delete access |
| `{resource}:*` | All permissions for resource |
| `*` | Full access (use sparingly!) |

### Wildcard Scopes

```php
// All post permissions
'scopes' => ['posts:*']

// Read access to all resources
'scopes' => ['*:read']

// Full access (admin only!)
'scopes' => ['*']
```

### Scope Enforcement

Protect routes with scope middleware:

```php
Route::middleware('scope:posts:write')
    ->post('/posts', [PostController::class, 'store']);

Route::middleware('scope:posts:delete')
    ->delete('/posts/{id}', [PostController::class, 'destroy']);
```

### Check Scopes in Controllers

```php
public function store(Request $request)
{
    if (!$request->user()->tokenCan('posts:write')) {
        return response()->json([
            'error' => 'Insufficient permissions',
            'required_scope' => 'posts:write',
        ], 403);
    }

    return Post::create($request->validated());
}
```

## Rate Limiting

Keys are rate-limited based on tier:

```php
// config/api.php
'rate_limits' => [
    'free' => ['requests' => 1000, 'per' => 'hour'],
    'pro' => ['requests' => 10000, 'per' => 'hour'],
    'business' => ['requests' => 50000, 'per' => 'hour'],
    'enterprise' => ['requests' => null], // Unlimited
],
```

Rate limit headers included in responses:

```
X-RateLimit-Limit: 10000
X-RateLimit-Remaining: 9847
X-RateLimit-Reset: 1640995200
```

[Learn more about Rate Limiting →](/packages/api/rate-limiting)

## Key Expiration

### Set Expiration

```php
$apiKey = ApiKey::create([
    'expires_at' => now()->addMonths(6),
]);
```

### Check Expiration

```php
if ($apiKey->isExpired()) {
    return response()->json(['error' => 'API key expired'], 401);
}
```

### Auto-Cleanup

Expired keys are automatically cleaned up:

```bash
php artisan api:prune-expired-keys
```

## Environment-Specific Keys

### Test Keys

```php
$testKey = ApiKey::create([
    'name' => 'Development Key',
    'environment' => 'test',
]);

// Key prefix: sk_test_...
```

Test keys:
- Don't affect production data
- Higher rate limits
- Clearly marked in UI
- Easy to identify and delete

### Live Keys

```php
$liveKey = ApiKey::create([
    'environment' => 'live',
]);

// Key prefix: sk_live_...
```

## Middleware

### API Authentication

```php
Route::middleware('auth:api')->group(function () {
    // Protected routes
});
```

### Scope Enforcement

```php
use Mod\Api\Middleware\EnforceApiScope;

Route::middleware([EnforceApiScope::class.':posts:write'])
    ->post('/posts', [PostController::class, 'store']);
```

### Rate Limiting

```php
use Mod\Api\Middleware\RateLimitApi;

Route::middleware(RateLimitApi::class)->group(function () {
    // Rate-limited routes
});
```

## Security Best Practices

### 1. Minimum Required Scopes

```php
// ✅ Good - specific scopes
'scopes' => ['posts:read', 'categories:read']

// ❌ Bad - excessive permissions
'scopes' => ['*']
```

### 2. Rotate Regularly

```php
// Rotate every 90 days
if ($apiKey->created_at->diffInDays() > 90) {
    $newKey = $apiKey->rotate();
    // Notify user of new key
}
```

### 3. Use Separate Keys Per Client

```php
// ✅ Good - separate keys
ApiKey::create(['name' => 'iOS App']);
ApiKey::create(['name' => 'Android App']);
ApiKey::create(['name' => 'Web App']);

// ❌ Bad - shared key
ApiKey::create(['name' => 'All Mobile Apps']);
```

### 4. Set Expiration

```php
// ✅ Good - temporary access
'expires_at' => now()->addMonths(6)

// ❌ Bad - never expires
'expires_at' => null
```

### 5. Monitor Usage

```php
$usage = ApiKey::find($id)->usage()
    ->whereBetween('created_at', [now()->subDays(7), now()])
    ->count();

if ($usage > $threshold) {
    // Alert admin
}
```

## Testing

```php
<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use Mod\Api\Models\ApiKey;

class ApiKeyAuthTest extends TestCase
{
    public function test_authenticates_with_valid_key(): void
    {
        $apiKey = ApiKey::factory()->create([
            'scopes' => ['posts:read'],
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$apiKey->plaintext_key}",
        ])->getJson('/api/v1/posts');

        $response->assertOk();
    }

    public function test_rejects_invalid_key(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid_key',
        ])->getJson('/api/v1/posts');

        $response->assertUnauthorized();
    }

    public function test_enforces_scopes(): void
    {
        $apiKey = ApiKey::factory()->create([
            'scopes' => ['posts:read'], // No write permission
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$apiKey->plaintext_key}",
        ])->postJson('/api/v1/posts', ['title' => 'Test']);

        $response->assertForbidden();
    }
}
```

## Learn More

- [Rate Limiting →](/packages/api/rate-limiting)
- [Scopes →](/packages/api/scopes)
- [Webhooks →](/packages/api/webhooks)
- [API Reference →](/api/authentication)
