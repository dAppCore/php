# API Package

The API package provides a complete REST API with secure authentication, rate limiting, webhooks, and OpenAPI documentation.

## Installation

```bash
composer require host-uk/core-api
```

## Quick Start

```php
<?php

namespace Mod\Blog;

use Core\Events\ApiRoutesRegistering;

class Boot
{
    public static array $listens = [
        ApiRoutesRegistering::class => 'onApiRoutes',
    ];

    public function onApiRoutes(ApiRoutesRegistering $event): void
    {
        $event->routes(function () {
            Route::get('/posts', [Api\PostController::class, 'index']);
            Route::post('/posts', [Api\PostController::class, 'store']);
            Route::get('/posts/{id}', [Api\PostController::class, 'show']);
        });
    }
}
```

## Key Features

### Authentication & Security

- **[API Keys](/packages/api/authentication)** - Secure API key management with bcrypt hashing
- **[Scopes](/packages/api/scopes)** - Fine-grained permission system
- **[Rate Limiting](/packages/api/rate-limiting)** - Tier-based rate limits with Redis backend
- **[Key Rotation](/packages/api/authentication#rotation)** - Secure key rotation with grace periods

### Webhooks

- **[Webhook Endpoints](/packages/api/webhooks)** - Event-driven notifications
- **[Signatures](/packages/api/webhooks#signatures)** - HMAC-SHA256 signature verification
- **[Delivery Tracking](/packages/api/webhooks#delivery)** - Retry logic and delivery history

### Documentation

- **[OpenAPI Spec](/packages/api/openapi)** - Auto-generated OpenAPI 3.0 documentation
- **[Interactive Docs](/packages/api/documentation)** - Swagger UI, Scalar, and ReDoc interfaces
- **[Code Examples](/packages/api/documentation#examples)** - Multi-language code snippets

### Monitoring

- **[Usage Analytics](/packages/api/analytics)** - Track API usage and quota
- **[Usage Alerts](/packages/api/alerts)** - Automated high-usage notifications
- **[Request Logging](/packages/api/logging)** - Comprehensive request/response logging

## Authentication

### Creating API Keys

```php
use Mod\Api\Models\ApiKey;

$apiKey = ApiKey::create([
    'name' => 'Mobile App',
    'workspace_id' => $workspace->id,
    'scopes' => ['posts:read', 'posts:write'],
    'rate_limit_tier' => 'pro',
]);

// Get plaintext key (only shown once!)
$plaintext = $apiKey->plaintext_key; // sk_live_abc123...
```

### Using API Keys

```bash
curl -H "Authorization: Bearer sk_live_abc123..." \
     https://api.example.com/v1/posts
```

[Learn more about Authentication →](/packages/api/authentication)

## Rate Limiting

Tier-based rate limits with automatic enforcement:

```php
// config/api.php
'rate_limits' => [
    'free' => ['requests' => 1000, 'per' => 'hour'],
    'pro' => ['requests' => 10000, 'per' => 'hour'],
    'business' => ['requests' => 50000, 'per' => 'hour'],
    'enterprise' => ['requests' => null], // Unlimited
],
```

Rate limit headers included in every response:

```
X-RateLimit-Limit: 10000
X-RateLimit-Remaining: 9847
X-RateLimit-Reset: 1640995200
```

[Learn more about Rate Limiting →](/packages/api/rate-limiting)

## Webhooks

### Creating Webhooks

```php
use Mod\Api\Models\WebhookEndpoint;

$webhook = WebhookEndpoint::create([
    'url' => 'https://your-app.com/webhooks',
    'events' => ['post.created', 'post.updated'],
    'secret' => 'whsec_abc123...',
    'workspace_id' => $workspace->id,
]);
```

### Dispatching Events

```php
use Mod\Api\Services\WebhookService;

$service = app(WebhookService::class);

$service->dispatch('post.created', [
    'id' => $post->id,
    'title' => $post->title,
    'url' => route('posts.show', $post),
]);
```

### Verifying Signatures

```php
use Mod\Api\Services\WebhookSignature;

$signature = WebhookSignature::verify(
    payload: $request->getContent(),
    signature: $request->header('X-Webhook-Signature'),
    secret: $webhook->secret
);

if (!$signature) {
    abort(401, 'Invalid signature');
}
```

[Learn more about Webhooks →](/packages/api/webhooks)

## OpenAPI Documentation

Auto-generate OpenAPI documentation with attributes:

```php
use Mod\Api\Documentation\Attributes\ApiTag;
use Mod\Api\Documentation\Attributes\ApiParameter;
use Mod\Api\Documentation\Attributes\ApiResponse;

#[ApiTag('Posts')]
class PostController extends Controller
{
    #[ApiParameter(name: 'page', in: 'query', type: 'integer')]
    #[ApiParameter(name: 'per_page', in: 'query', type: 'integer')]
    #[ApiResponse(status: 200, description: 'List of posts')]
    public function index(Request $request)
    {
        return PostResource::collection(
            Post::paginate($request->input('per_page', 15))
        );
    }
}
```

View documentation at:
- `/api/docs` - Swagger UI
- `/api/docs/scalar` - Scalar interface
- `/api/docs/redoc` - ReDoc interface

[Learn more about Documentation →](/packages/api/documentation)

## API Resources

Transform models to JSON:

```php
<?php

namespace Mod\Blog\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'content' => $this->when(
                $request->user()->tokenCan('posts:read-content'),
                $this->content
            ),
            'status' => $this->status,
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
```

## Configuration

```php
// config/api.php
return [
    'prefix' => 'api/v1',
    'middleware' => ['api'],

    'rate_limits' => [
        'free' => ['requests' => 1000, 'per' => 'hour'],
        'pro' => ['requests' => 10000, 'per' => 'hour'],
        'business' => ['requests' => 50000, 'per' => 'hour'],
        'enterprise' => ['requests' => null],
    ],

    'api_keys' => [
        'hash_algo' => 'bcrypt',
        'prefix' => 'sk',
        'length' => 32,
    ],

    'webhooks' => [
        'max_retries' => 3,
        'retry_delay' => 60, // seconds
        'signature_algo' => 'sha256',
    ],

    'documentation' => [
        'enabled' => true,
        'middleware' => ['web', 'auth'],
        'title' => 'API Documentation',
    ],
];
```

## Best Practices

### 1. Use API Resources

```php
// ✅ Good - API resource
return PostResource::collection($posts);

// ❌ Bad - raw model data
return $posts->toArray();
```

### 2. Implement Scopes

```php
// ✅ Good - scope protection
Route::middleware('scope:posts:write')
    ->post('/posts', [PostController::class, 'store']);
```

### 3. Verify Webhook Signatures

```php
// ✅ Good - verify signature
if (!WebhookSignature::verify($payload, $signature, $secret)) {
    abort(401);
}
```

### 4. Use Rate Limit Middleware

```php
// ✅ Good - rate limited
Route::middleware('api.rate-limit')
    ->group(function () {
        // API routes
    });
```

## Testing

```php
<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use Mod\Api\Models\ApiKey;

class PostApiTest extends TestCase
{
    public function test_lists_posts(): void
    {
        $apiKey = ApiKey::factory()->create([
            'scopes' => ['posts:read'],
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$apiKey->plaintext_key}",
        ])->getJson('/api/v1/posts');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'title', 'slug'],
                ],
            ]);
    }
}
```

## Learn More

- [Authentication →](/packages/api/authentication)
- [Rate Limiting →](/packages/api/rate-limiting)
- [Webhooks →](/packages/api/webhooks)
- [OpenAPI Docs →](/packages/api/documentation)
- [API Reference →](/api/endpoints)
