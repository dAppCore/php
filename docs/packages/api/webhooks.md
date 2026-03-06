# Webhooks

The API package provides event-driven webhooks with HMAC-SHA256 signatures, automatic retries, and delivery tracking.

## Overview

Webhooks allow your application to:
- Send real-time notifications to external systems
- Trigger workflows in other applications
- Sync data across platforms
- Build integrations without polling

## Creating Webhooks

### Basic Webhook

```php
use Mod\Api\Models\WebhookEndpoint;

$webhook = WebhookEndpoint::create([
    'url' => 'https://your-app.com/webhooks',
    'events' => ['post.created', 'post.updated'],
    'secret' => 'whsec_'.Str::random(32),
    'workspace_id' => $workspace->id,
    'is_active' => true,
]);
```

### With Filters

```php
$webhook = WebhookEndpoint::create([
    'url' => 'https://your-app.com/webhooks/posts',
    'events' => ['post.*'], // All post events
    'filters' => [
        'status' => 'published', // Only published posts
    ],
]);
```

## Dispatching Events

### Manual Dispatch

```php
use Mod\Api\Services\WebhookService;

$webhookService = app(WebhookService::class);

$webhookService->dispatch('post.created', [
    'id' => $post->id,
    'title' => $post->title,
    'url' => route('posts.show', $post),
    'published_at' => $post->published_at,
]);
```

### From Model Events

```php
use Mod\Api\Services\WebhookService;

class Post extends Model
{
    protected static function booted(): void
    {
        static::created(function (Post $post) {
            app(WebhookService::class)->dispatch('post.created', [
                'id' => $post->id,
                'title' => $post->title,
            ]);
        });

        static::updated(function (Post $post) {
            app(WebhookService::class)->dispatch('post.updated', [
                'id' => $post->id,
                'title' => $post->title,
            ]);
        });
    }
}
```

### From Actions

```php
use Mod\Blog\Actions\CreatePost;
use Mod\Api\Services\WebhookService;

class CreatePost
{
    use Action;

    public function handle(array $data): Post
    {
        $post = Post::create($data);

        // Dispatch webhook
        app(WebhookService::class)->dispatch('post.created', [
            'post' => $post->only(['id', 'title', 'slug']),
        ]);

        return $post;
    }
}
```

## Webhook Payload

### Standard Format

```json
{
  "id": "evt_abc123def456",
  "type": "post.created",
  "created_at": "2024-01-15T10:30:00Z",
  "data": {
    "object": {
      "id": 123,
      "title": "My Blog Post",
      "url": "https://example.com/posts/my-blog-post"
    }
  },
  "workspace_id": 456
}
```

### Custom Payload

```php
$webhookService->dispatch('post.published', [
    'post_id' => $post->id,
    'title' => $post->title,
    'author' => [
        'id' => $post->author->id,
        'name' => $post->author->name,
    ],
    'metadata' => [
        'published_at' => $post->published_at,
        'word_count' => str_word_count($post->content),
    ],
]);
```

## Webhook Signatures

All webhook requests include HMAC-SHA256 signatures:

### Request Headers

```
X-Webhook-Signature: sha256=abc123def456...
X-Webhook-Timestamp: 1640995200
X-Webhook-ID: evt_abc123
```

### Verifying Signatures

```php
use Mod\Api\Services\WebhookSignature;

public function handle(Request $request)
{
    $payload = $request->getContent();
    $signature = $request->header('X-Webhook-Signature');
    $secret = $webhook->secret;

    if (!WebhookSignature::verify($payload, $signature, $secret)) {
        abort(401, 'Invalid signature');
    }

    // Process webhook...
}
```

### Manual Verification

```php
$expectedSignature = 'sha256=' . hash_hmac(
    'sha256',
    $payload,
    $secret
);

if (!hash_equals($expectedSignature, $providedSignature)) {
    abort(401);
}
```

## Webhook Delivery

### Automatic Retries

Failed deliveries are automatically retried:

```php
// config/api.php
'webhooks' => [
    'max_retries' => 3,
    'retry_delay' => 60, // seconds
    'timeout' => 10,
],
```

Retry schedule:
1. Immediate delivery
2. After 1 minute
3. After 5 minutes
4. After 30 minutes

### Delivery Status

```php
$deliveries = $webhook->deliveries()
    ->latest()
    ->limit(10)
    ->get();

foreach ($deliveries as $delivery) {
    echo $delivery->status;      // success, failed, pending
    echo $delivery->status_code;  // HTTP status code
    echo $delivery->attempts;     // Number of attempts
    echo $delivery->response_body; // Response from endpoint
}
```

### Manual Retry

```php
use Mod\Api\Models\WebhookDelivery;

$delivery = WebhookDelivery::find($id);

if ($delivery->isFailed()) {
    $delivery->retry();
}
```

## Webhook Events

### Common Events

| Event | Description |
|-------|-------------|
| `{resource}.created` | Resource created |
| `{resource}.updated` | Resource updated |
| `{resource}.deleted` | Resource deleted |
| `{resource}.published` | Resource published |
| `{resource}.archived` | Resource archived |

### Wildcards

```php
// All post events
'events' => ['post.*']

// All events
'events' => ['*']

// Specific events
'events' => ['post.created', 'post.published']
```

## Testing Webhooks

### Test Endpoint

```php
use Mod\Api\Models\WebhookEndpoint;

$webhook = WebhookEndpoint::find($id);

$result = $webhook->test([
    'test' => true,
    'message' => 'This is a test webhook',
]);

if ($result['success']) {
    echo "Test successful! Status: {$result['status_code']}";
} else {
    echo "Test failed: {$result['error']}";
}
```

### Mock Webhooks in Tests

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use Mod\Api\Facades\Webhooks;

class PostCreationTest extends TestCase
{
    public function test_dispatches_webhook_on_create(): void
    {
        Webhooks::fake();

        $post = Post::create(['title' => 'Test']);

        Webhooks::assertDispatched('post.created', function ($event, $payload) {
            return $payload['id'] === $post->id;
        });
    }
}
```

## Webhook Consumers

### Receiving Webhooks (PHP)

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function handle(Request $request)
    {
        // Verify signature
        if (!$this->verifySignature($request)) {
            abort(401, 'Invalid signature');
        }

        $event = $request->input('type');
        $data = $request->input('data');

        match ($event) {
            'post.created' => $this->handlePostCreated($data),
            'post.updated' => $this->handlePostUpdated($data),
            default => null,
        };

        return response()->json(['received' => true]);
    }

    protected function verifySignature(Request $request): bool
    {
        $payload = $request->getContent();
        $signature = $request->header('X-Webhook-Signature');
        $secret = config('webhooks.secret');

        $expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $signature);
    }
}
```

### Receiving Webhooks (JavaScript/Node.js)

```javascript
const express = require('express');
const crypto = require('crypto');

app.post('/webhooks', express.raw({type: 'application/json'}), (req, res) => {
  const payload = req.body;
  const signature = req.headers['x-webhook-signature'];
  const secret = process.env.WEBHOOK_SECRET;

  // Verify signature
  const expected = 'sha256=' + crypto
    .createHmac('sha256', secret)
    .update(payload)
    .digest('hex');

  if (!crypto.timingSafeEqual(Buffer.from(signature), Buffer.from(expected))) {
    return res.status(401).send('Invalid signature');
  }

  const event = JSON.parse(payload);

  switch (event.type) {
    case 'post.created':
      handlePostCreated(event.data);
      break;
    case 'post.updated':
      handlePostUpdated(event.data);
      break;
  }

  res.json({received: true});
});
```

## Webhook Management UI

### List Webhooks

```php
$webhooks = WebhookEndpoint::where('workspace_id', $workspace->id)->get();
```

### Enable/Disable

```php
$webhook->update(['is_active' => false]); // Disable
$webhook->update(['is_active' => true]);  // Enable
```

### View Deliveries

```php
$deliveries = $webhook->deliveries()
    ->with('webhookEndpoint')
    ->latest()
    ->paginate(50);
```

## Best Practices

### 1. Verify Signatures

```php
// ✅ Good - always verify
if (!WebhookSignature::verify($payload, $signature, $secret)) {
    abort(401);
}
```

### 2. Return 200 Quickly

```php
// ✅ Good - queue long-running tasks
public function handle(Request $request)
{
    // Verify signature
    if (!$this->verifySignature($request)) {
        abort(401);
    }

    // Queue processing
    ProcessWebhook::dispatch($request->all());

    return response()->json(['received' => true]);
}
```

### 3. Handle Idempotency

```php
// ✅ Good - check for duplicate events
public function handle(Request $request)
{
    $eventId = $request->input('id');

    if (ProcessedWebhook::where('event_id', $eventId)->exists()) {
        return response()->json(['received' => true]); // Already processed
    }

    // Process webhook...

    ProcessedWebhook::create(['event_id' => $eventId]);
}
```

### 4. Use Webhook Secrets

```php
// ✅ Good - secure secret
'secret' => 'whsec_' . Str::random(32)

// ❌ Bad - weak secret
'secret' => 'password123'
```

## Troubleshooting

### Webhook Not Firing

1. Check if webhook is active: `$webhook->is_active`
2. Verify event name matches: `'post.created'` not `'posts.created'`
3. Check workspace context is set
4. Review event filters

### Delivery Failures

1. Check endpoint URL is reachable
2. Verify SSL certificate is valid
3. Check firewall/IP whitelist
4. Review timeout settings

### Signature Verification Fails

1. Ensure using raw request body (not parsed JSON)
2. Check secret matches on both sides
3. Verify using same hashing algorithm (SHA-256)
4. Check for whitespace/encoding issues

## Learn More

- [API Authentication →](/packages/api/authentication)
- [Webhook Security →](/api/authentication#webhook-signatures)
- [API Reference →](/api/endpoints#webhook-endpoints)
