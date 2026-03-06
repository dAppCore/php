# Webhook Integration Guide

This guide explains how to receive and process webhooks from the core-api package. Learn to verify signatures, handle retries, and implement reliable webhook consumers.

## Overview

Webhooks provide real-time notifications when events occur in the system. Instead of polling the API, your application receives HTTP POST requests with event data.

**Key Features:**
- HMAC-SHA256 signature verification
- Automatic retries with exponential backoff
- Timestamp validation for replay protection
- Delivery tracking and manual retry

## Webhook Payload Format

All webhooks follow a consistent format:

```json
{
  "id": "evt_abc123def456789",
  "type": "post.created",
  "created_at": "2026-01-15T10:30:00Z",
  "data": {
    "id": 123,
    "title": "New Blog Post",
    "status": "published",
    "author_id": 42
  },
  "workspace_id": 456
}
```

**Fields:**
- `id` - Unique event identifier (use for idempotency)
- `type` - Event type (e.g., `post.created`, `user.updated`)
- `created_at` - ISO 8601 timestamp when the event occurred
- `data` - Event-specific payload
- `workspace_id` - Workspace that generated the event

## Webhook Headers

Every webhook request includes these headers:

| Header | Description | Example |
|--------|-------------|---------|
| `Content-Type` | Always `application/json` | `application/json` |
| `X-Webhook-Id` | Unique event ID | `evt_abc123def456` |
| `X-Webhook-Event` | Event type | `post.created` |
| `X-Webhook-Timestamp` | Unix timestamp | `1705312200` |
| `X-Webhook-Signature` | HMAC-SHA256 signature | `a1b2c3d4e5f6...` |

## Signature Verification

**Always verify webhook signatures** to ensure requests are authentic and unmodified.

### Signature Algorithm

The signature is computed as:

```
signature = HMAC-SHA256(timestamp + "." + payload, secret)
```

Where:
- `timestamp` is the value of `X-Webhook-Timestamp` header
- `payload` is the raw request body (JSON string)
- `secret` is your webhook signing secret

### Verification Steps

1. Get the signature and timestamp from headers
2. Get the raw request body (do not parse JSON first)
3. Compute expected signature: `HMAC-SHA256(timestamp + "." + body, secret)`
4. Compare signatures using timing-safe comparison
5. Verify timestamp is within 5 minutes of current time

## Code Examples

### PHP (Laravel)

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * Handle incoming webhooks.
     */
    public function handle(Request $request)
    {
        // Step 1: Verify the signature
        if (!$this->verifySignature($request)) {
            Log::warning('Invalid webhook signature', [
                'ip' => $request->ip(),
            ]);
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        // Step 2: Verify timestamp (replay protection)
        if (!$this->verifyTimestamp($request)) {
            Log::warning('Webhook timestamp too old');
            return response()->json(['error' => 'Timestamp expired'], 401);
        }

        // Step 3: Check for duplicate events (idempotency)
        $eventId = $request->input('id');
        if ($this->isDuplicate($eventId)) {
            // Already processed - return success to stop retries
            return response()->json(['received' => true]);
        }

        // Step 4: Process the event
        try {
            $this->processEvent(
                $request->input('type'),
                $request->input('data')
            );

            // Mark event as processed
            $this->markProcessed($eventId);

            return response()->json(['received' => true]);

        } catch (\Exception $e) {
            Log::error('Webhook processing failed', [
                'event_id' => $eventId,
                'error' => $e->getMessage(),
            ]);

            // Return 500 to trigger retry
            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * Verify the HMAC-SHA256 signature.
     */
    protected function verifySignature(Request $request): bool
    {
        $signature = $request->header('X-Webhook-Signature');
        $timestamp = $request->header('X-Webhook-Timestamp');
        $payload = $request->getContent();
        $secret = config('services.webhooks.secret');

        if (!$signature || !$timestamp) {
            return false;
        }

        // Compute expected signature
        $signedPayload = $timestamp . '.' . $payload;
        $expectedSignature = hash_hmac('sha256', $signedPayload, $secret);

        // Use timing-safe comparison
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Verify timestamp is within tolerance (5 minutes).
     */
    protected function verifyTimestamp(Request $request): bool
    {
        $timestamp = (int) $request->header('X-Webhook-Timestamp');
        $tolerance = 300; // 5 minutes

        return abs(time() - $timestamp) <= $tolerance;
    }

    /**
     * Check if event was already processed.
     */
    protected function isDuplicate(string $eventId): bool
    {
        return cache()->has("webhook:processed:{$eventId}");
    }

    /**
     * Mark event as processed (cache for 24 hours).
     */
    protected function markProcessed(string $eventId): void
    {
        cache()->put("webhook:processed:{$eventId}", true, now()->addDay());
    }

    /**
     * Process the webhook event.
     */
    protected function processEvent(string $type, array $data): void
    {
        match ($type) {
            'post.created' => $this->handlePostCreated($data),
            'post.updated' => $this->handlePostUpdated($data),
            'post.deleted' => $this->handlePostDeleted($data),
            'user.created' => $this->handleUserCreated($data),
            default => Log::info("Unhandled webhook type: {$type}"),
        };
    }

    protected function handlePostCreated(array $data): void
    {
        // Sync to your database, trigger notifications, etc.
        Log::info('Post created', $data);
    }

    protected function handlePostUpdated(array $data): void
    {
        Log::info('Post updated', $data);
    }

    protected function handlePostDeleted(array $data): void
    {
        Log::info('Post deleted', $data);
    }

    protected function handleUserCreated(array $data): void
    {
        Log::info('User created', $data);
    }
}
```

**Route registration:**

```php
// routes/api.php
Route::post('/webhooks', [WebhookController::class, 'handle'])
    ->middleware('throttle:100,1'); // Rate limit webhook endpoint
```

### JavaScript (Node.js/Express)

```javascript
const express = require('express');
const crypto = require('crypto');
const app = express();

// Important: Use raw body for signature verification
app.post('/webhooks', express.raw({ type: 'application/json' }), async (req, res) => {
  const signature = req.headers['x-webhook-signature'];
  const timestamp = req.headers['x-webhook-timestamp'];
  const payload = req.body; // Raw buffer
  const secret = process.env.WEBHOOK_SECRET;

  // Step 1: Verify signature
  if (!verifySignature(payload, signature, timestamp, secret)) {
    console.warn('Invalid webhook signature');
    return res.status(401).json({ error: 'Invalid signature' });
  }

  // Step 2: Verify timestamp
  if (!verifyTimestamp(timestamp)) {
    console.warn('Webhook timestamp too old');
    return res.status(401).json({ error: 'Timestamp expired' });
  }

  // Step 3: Parse the event
  let event;
  try {
    event = JSON.parse(payload.toString());
  } catch (e) {
    return res.status(400).json({ error: 'Invalid JSON' });
  }

  // Step 4: Check for duplicates
  if (await isDuplicate(event.id)) {
    return res.json({ received: true });
  }

  // Step 5: Process the event
  try {
    await processEvent(event.type, event.data);
    await markProcessed(event.id);
    res.json({ received: true });
  } catch (e) {
    console.error('Webhook processing failed:', e);
    res.status(500).json({ error: 'Processing failed' });
  }
});

function verifySignature(payload, signature, timestamp, secret) {
  if (!signature || !timestamp) return false;

  const signedPayload = timestamp + '.' + payload.toString();
  const expectedSignature = crypto
    .createHmac('sha256', secret)
    .update(signedPayload)
    .digest('hex');

  // Timing-safe comparison
  try {
    return crypto.timingSafeEqual(
      Buffer.from(signature),
      Buffer.from(expectedSignature)
    );
  } catch {
    return false;
  }
}

function verifyTimestamp(timestamp) {
  const tolerance = 300; // 5 minutes
  const now = Math.floor(Date.now() / 1000);
  return Math.abs(now - parseInt(timestamp)) <= tolerance;
}

// Redis-based duplicate detection
const Redis = require('ioredis');
const redis = new Redis();

async function isDuplicate(eventId) {
  return await redis.exists(`webhook:processed:${eventId}`);
}

async function markProcessed(eventId) {
  await redis.set(`webhook:processed:${eventId}`, '1', 'EX', 86400);
}

async function processEvent(type, data) {
  switch (type) {
    case 'post.created':
      console.log('Post created:', data);
      break;
    case 'post.updated':
      console.log('Post updated:', data);
      break;
    case 'post.deleted':
      console.log('Post deleted:', data);
      break;
    default:
      console.log(`Unhandled event type: ${type}`);
  }
}

app.listen(3000);
```

### Python (Flask)

```python
import hmac
import hashlib
import time
import json
from functools import wraps
from flask import Flask, request, jsonify
import redis

app = Flask(__name__)
cache = redis.Redis()

WEBHOOK_SECRET = 'your_webhook_secret'
TIMESTAMP_TOLERANCE = 300  # 5 minutes

def verify_webhook(f):
    """Decorator to verify webhook signatures."""
    @wraps(f)
    def decorated(*args, **kwargs):
        signature = request.headers.get('X-Webhook-Signature')
        timestamp = request.headers.get('X-Webhook-Timestamp')
        payload = request.get_data()

        # Verify signature
        if not verify_signature(payload, signature, timestamp):
            return jsonify({'error': 'Invalid signature'}), 401

        # Verify timestamp
        if not verify_timestamp(timestamp):
            return jsonify({'error': 'Timestamp expired'}), 401

        return f(*args, **kwargs)
    return decorated


def verify_signature(payload: bytes, signature: str, timestamp: str) -> bool:
    """Verify the HMAC-SHA256 signature."""
    if not signature or not timestamp:
        return False

    signed_payload = f"{timestamp}.{payload.decode('utf-8')}"
    expected_signature = hmac.new(
        WEBHOOK_SECRET.encode('utf-8'),
        signed_payload.encode('utf-8'),
        hashlib.sha256
    ).hexdigest()

    # Timing-safe comparison
    return hmac.compare_digest(expected_signature, signature)


def verify_timestamp(timestamp: str) -> bool:
    """Verify timestamp is within tolerance."""
    try:
        ts = int(timestamp)
        return abs(time.time() - ts) <= TIMESTAMP_TOLERANCE
    except (ValueError, TypeError):
        return False


def is_duplicate(event_id: str) -> bool:
    """Check if event was already processed."""
    return cache.exists(f"webhook:processed:{event_id}")


def mark_processed(event_id: str) -> None:
    """Mark event as processed (24 hour TTL)."""
    cache.setex(f"webhook:processed:{event_id}", 86400, "1")


@app.route('/webhooks', methods=['POST'])
@verify_webhook
def handle_webhook():
    event = request.get_json()
    event_id = event.get('id')
    event_type = event.get('type')
    data = event.get('data')

    # Check for duplicates
    if is_duplicate(event_id):
        return jsonify({'received': True})

    # Process the event
    try:
        process_event(event_type, data)
        mark_processed(event_id)
        return jsonify({'received': True})
    except Exception as e:
        app.logger.error(f"Webhook processing failed: {e}")
        return jsonify({'error': 'Processing failed'}), 500


def process_event(event_type: str, data: dict) -> None:
    """Process webhook event based on type."""
    handlers = {
        'post.created': handle_post_created,
        'post.updated': handle_post_updated,
        'post.deleted': handle_post_deleted,
        'user.created': handle_user_created,
    }

    handler = handlers.get(event_type)
    if handler:
        handler(data)
    else:
        app.logger.info(f"Unhandled event type: {event_type}")


def handle_post_created(data: dict) -> None:
    app.logger.info(f"Post created: {data}")


def handle_post_updated(data: dict) -> None:
    app.logger.info(f"Post updated: {data}")


def handle_post_deleted(data: dict) -> None:
    app.logger.info(f"Post deleted: {data}")


def handle_user_created(data: dict) -> None:
    app.logger.info(f"User created: {data}")


if __name__ == '__main__':
    app.run(port=3000)
```

## Retry Handling

### Retry Schedule

Failed webhook deliveries are automatically retried with exponential backoff:

| Attempt | Delay | Total Time |
|---------|-------|------------|
| 1 | Immediate | 0 |
| 2 | 1 minute | 1 minute |
| 3 | 5 minutes | 6 minutes |
| 4 | 30 minutes | 36 minutes |
| 5 | 2 hours | 2h 36m |
| 6 (final) | 24 hours | 26h 36m |

After 6 failed attempts, the delivery is marked as permanently failed.

### Triggering Retries

A delivery is retried when your endpoint returns:
- **5xx status codes** (server errors)
- **Connection timeouts** (30 second default)
- **Connection refused/failed**

A delivery is **not** retried when:
- **2xx status codes** (success)
- **4xx status codes** (client errors - your endpoint rejected it)

### Best Practices for Reliability

**1. Return 200 Quickly**

Process webhooks asynchronously to avoid timeouts:

```php
public function handle(Request $request)
{
    // Verify signature first
    if (!$this->verifySignature($request)) {
        return response()->json(['error' => 'Invalid signature'], 401);
    }

    // Queue for async processing
    ProcessWebhook::dispatch($request->all());

    // Return immediately
    return response()->json(['received' => true]);
}
```

**2. Handle Duplicates**

Webhooks may be delivered more than once. Always check the event ID:

```php
public function handle(Request $request)
{
    $eventId = $request->input('id');

    // Atomic check-and-set
    if (!Cache::add("webhook:{$eventId}", true, now()->addDay())) {
        // Already processed
        return response()->json(['received' => true]);
    }

    // Process the event...
}
```

**3. Return 4xx for Permanent Failures**

If your endpoint cannot process an event (invalid data, etc.), return 4xx to stop retries:

```php
public function handle(Request $request)
{
    $eventType = $request->input('type');

    // Unknown event type - don't retry
    if (!in_array($eventType, $this->supportedEvents)) {
        return response()->json(['error' => 'Unknown event type'], 400);
    }

    // Process...
}
```

## Event Types

### Common Events

| Event | Description |
|-------|-------------|
| `{resource}.created` | Resource was created |
| `{resource}.updated` | Resource was updated |
| `{resource}.deleted` | Resource was deleted |
| `{resource}.published` | Resource was published |
| `{resource}.archived` | Resource was archived |

### Wildcard Subscriptions

Subscribe to all events for a resource:

```php
$webhook = WebhookEndpoint::create([
    'url' => 'https://your-app.com/webhooks',
    'events' => ['post.*'], // All post events
    'secret' => 'whsec_' . Str::random(32),
]);
```

Subscribe to all events:

```php
$webhook = WebhookEndpoint::create([
    'url' => 'https://your-app.com/webhooks',
    'events' => ['*'], // All events
    'secret' => 'whsec_' . Str::random(32),
]);
```

### High-Volume Events

Some events are high-volume and opt-in only:

- `link.clicked` - Link click tracking
- `qrcode.scanned` - QR code scan tracking

These must be explicitly included in the `events` array.

## Testing Webhooks

### Test Endpoint

Use the test endpoint to verify your webhook handler:

```bash
curl -X POST https://api.example.com/v1/webhooks/{webhook_id}/test \
  -H "Authorization: Bearer sk_live_abc123"
```

This sends a test event to your endpoint.

### Local Development

For local development, use a tunnel service:

**ngrok:**
```bash
ngrok http 3000
# Use the https URL as your webhook endpoint
```

**Cloudflare Tunnel:**
```bash
cloudflared tunnel --url http://localhost:3000
```

### Mock Verification

Test signature verification in isolation:

```php
// tests/Feature/WebhookTest.php
public function test_verifies_valid_signature(): void
{
    $payload = json_encode([
        'id' => 'evt_test123',
        'type' => 'post.created',
        'data' => ['id' => 1, 'title' => 'Test'],
    ]);

    $timestamp = time();
    $secret = 'test_secret';
    $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

    config(['services.webhooks.secret' => $secret]);

    $response = $this->postJson('/webhooks', json_decode($payload, true), [
        'X-Webhook-Signature' => $signature,
        'X-Webhook-Timestamp' => $timestamp,
        'Content-Type' => 'application/json',
    ]);

    $response->assertOk();
}

public function test_rejects_invalid_signature(): void
{
    $response = $this->postJson('/webhooks', [
        'id' => 'evt_test123',
        'type' => 'post.created',
    ], [
        'X-Webhook-Signature' => 'invalid',
        'X-Webhook-Timestamp' => time(),
    ]);

    $response->assertUnauthorized();
}
```

## Troubleshooting

### Signature Verification Fails

**Common causes:**

1. **Parsed JSON instead of raw body**
   ```php
   // Wrong - body has been modified
   $payload = json_encode($request->all());

   // Correct - raw body
   $payload = $request->getContent();
   ```

2. **Different secrets**
   - Check the secret matches exactly
   - Ensure no extra whitespace

3. **Encoding issues**
   ```php
   // Ensure UTF-8 encoding
   $payload = $request->getContent();
   $signedPayload = $timestamp . '.' . $payload;
   ```

### Deliveries Not Arriving

1. **Check endpoint URL** - Must be publicly accessible (not localhost)
2. **Check SSL certificate** - Must be valid and not expired
3. **Check firewall rules** - Allow incoming HTTPS from webhook IPs
4. **Check webhook is active** - Endpoints can be disabled after failures

### Timeouts

The default timeout is 30 seconds. If processing takes longer:

```php
// Queue long-running tasks
public function handle(Request $request)
{
    // Quick signature check
    if (!$this->verifySignature($request)) {
        return response()->json(['error' => 'Invalid signature'], 401);
    }

    // Queue for async processing
    ProcessWebhook::dispatch($request->all());

    // Return immediately
    return response()->json(['received' => true]);
}
```

## Security Considerations

### Always Verify Signatures

Never skip signature verification, even in development:

```php
// DON'T DO THIS
if (app()->environment('local')) {
    return; // Skip verification
}
```

### Use HTTPS

Webhook endpoints must use HTTPS to protect:
- The webhook secret in transit
- Sensitive payload data

### Protect Your Secret

- Store in environment variables, not code
- Rotate secrets periodically
- Use different secrets per environment

### Rate Limit Your Endpoint

Protect against abuse:

```php
Route::post('/webhooks', [WebhookController::class, 'handle'])
    ->middleware('throttle:100,1'); // 100 requests per minute
```

## Learn More

- [Webhooks Overview](/packages/api/webhooks) - Creating webhook endpoints
- [Authentication](/packages/api/authentication) - API key management
- [Rate Limiting](/packages/api/rate-limiting) - Understanding rate limits
