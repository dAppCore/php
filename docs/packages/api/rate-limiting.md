# Rate Limiting

The API package provides tier-based rate limiting with Redis backend, custom limits per endpoint, and automatic enforcement.

## Overview

Rate limiting:
- Prevents API abuse
- Ensures fair usage
- Protects server resources
- Enforces tier limits

## Tier-Based Limits

Configure limits per tier:

```php
// config/api.php
'rate_limits' => [
    'free' => [
        'requests' => 1000,
        'per' => 'hour',
    ],
    'pro' => [
        'requests' => 10000,
        'per' => 'hour',
    ],
    'business' => [
        'requests' => 50000,
        'per' => 'hour',
    ],
    'enterprise' => [
        'requests' => null, // Unlimited
    ],
],
```

## Response Headers

Every response includes rate limit headers:

```
X-RateLimit-Limit: 10000
X-RateLimit-Remaining: 9847
X-RateLimit-Reset: 1640995200
```

## Applying Rate Limits

### Global Rate Limiting

```php
// Apply to all API routes
Route::middleware('api.rate-limit')->group(function () {
    Route::get('/posts', [PostController::class, 'index']);
    Route::post('/posts', [PostController::class, 'store']);
});
```

### Per-Endpoint Limits

```php
// Custom limit for specific endpoint
Route::get('/search', [SearchController::class, 'index'])
    ->middleware('throttle:60,1'); // 60 per minute
```

### Named Rate Limiters

```php
// app/Providers/RouteServiceProvider.php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
});

// Apply in routes
Route::middleware('throttle:api')->group(function () {
    // Routes
});
```

## Custom Rate Limiting

### Based on API Key Tier

```php
use Mod\Api\Services\RateLimitService;

$rateLimitService = app(RateLimitService::class);

$result = $rateLimitService->attempt($apiKey);

if ($result->exceeded()) {
    return response()->json([
        'error' => 'Rate limit exceeded',
        'retry_after' => $result->retryAfter(),
    ], 429);
}
```

### Dynamic Limits

```php
RateLimiter::for('api', function (Request $request) {
    $apiKey = $request->user()->currentApiKey();

    return match ($apiKey->rate_limit_tier) {
        'free' => Limit::perHour(1000),
        'pro' => Limit::perHour(10000),
        'business' => Limit::perHour(50000),
        'enterprise' => Limit::none(),
    };
});
```

## Rate Limit Responses

### 429 Too Many Requests

```json
{
  "message": "Too many requests",
  "error_code": "RATE_LIMIT_EXCEEDED",
  "retry_after": 3600,
  "limit": 10000,
  "remaining": 0,
  "reset_at": "2024-01-15T12:00:00Z"
}
```

### Retry-After Header

```
HTTP/1.1 429 Too Many Requests
Retry-After: 3600
X-RateLimit-Limit: 10000
X-RateLimit-Remaining: 0
X-RateLimit-Reset: 1640995200
```

## Monitoring

### Check Current Usage

```php
use Mod\Api\Services\RateLimitService;

$service = app(RateLimitService::class);

$usage = $service->getCurrentUsage($apiKey);

echo "Used: {$usage->used} / {$usage->limit}";
echo "Remaining: {$usage->remaining}";
echo "Resets at: {$usage->reset_at}";
```

### Usage Analytics

```php
$apiKey = ApiKey::find($id);

$stats = $apiKey->usage()
    ->whereBetween('created_at', [now()->subDays(7), now()])
    ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
    ->groupBy('date')
    ->get();
```

## Best Practices

### 1. Handle 429 Gracefully

```javascript
// ✅ Good - retry with backoff
async function apiRequest(url, retries = 3) {
  for (let i = 0; i < retries; i++) {
    const response = await fetch(url);

    if (response.status === 429) {
      const retryAfter = parseInt(response.headers.get('Retry-After'));
      await sleep(retryAfter * 1000);
      continue;
    }

    return response;
  }
}
```

### 2. Respect Rate Limit Headers

```javascript
// ✅ Good - check remaining requests
const remaining = parseInt(response.headers.get('X-RateLimit-Remaining'));

if (remaining < 10) {
  console.warn('Approaching rate limit');
}
```

### 3. Implement Exponential Backoff

```javascript
// ✅ Good - exponential backoff
async function fetchWithBackoff(url, maxRetries = 5) {
  for (let i = 0; i < maxRetries; i++) {
    const response = await fetch(url);

    if (response.status !== 429) {
      return response;
    }

    const delay = Math.min(1000 * Math.pow(2, i), 30000);
    await sleep(delay);
  }
}
```

### 4. Use Caching

```javascript
// ✅ Good - cache responses
const cache = new Map();

async function fetchPost(id) {
  const cached = cache.get(id);
  if (cached && Date.now() - cached.timestamp < 60000) {
    return cached.data;
  }

  const response = await fetch(`/api/v1/posts/${id}`);
  const data = await response.json();

  cache.set(id, {data, timestamp: Date.now()});
  return data;
}
```

## Learn More

- [API Authentication →](/packages/api/authentication)
- [Error Handling →](/api/errors)
- [API Reference →](/api/endpoints#rate-limiting)
