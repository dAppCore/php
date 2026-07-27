# Core\Webhook

Inbound webhook receiving, signature verification, and event dispatch.

## Key Classes

| Class | Purpose |
|-------|---------|
| `Boot` | Service provider: merges config, registers `POST /webhooks/{source}` API route via `ApiRoutesRegistering` event |
| `WebhookController` | Receives webhooks: verifies signature (if verifier bound), stores call, dispatches `WebhookReceived` event |
| `WebhookCall` | Eloquent model (ULID primary key): source, event_type, headers, payload, signature_valid, processed_at |
| `WebhookReceived` | Event dispatched after a webhook is stored |
| `WebhookVerifier` | Interface: `verify(Request $request, string $secret): bool` |
| `CronTrigger` | Cron-based webhook triggering |

## Request Flow

```
POST /api/webhooks/{source}
  |
  v
WebhookController::handle()
  |-> Look up verifier: app("webhook.verifier.{$source}")
  |-> Verify signature against config("webhook.secrets.{$source}")
  |-> Extract event type from payload (type/event_type/event)
  |-> Create WebhookCall record
  |-> Dispatch WebhookReceived event
  |-> Return {"ok": true}
```

## Adding a New Webhook Source

1. Implement `WebhookVerifier` for your source
2. Bind it: `$this->app->bind('webhook.verifier.stripe', StripeWebhookVerifier::class)`
3. Set secret in config: `webhook.secrets.stripe`
4. Listen for `WebhookReceived` event and filter by source

## WebhookCall Model

- Uses ULIDs (not UUIDs) for sortable, unique identifiers
- `$timestamps = false` -- has `created_at` cast but no `updated_at`
- Scopes: `unprocessed()`, `forSource($source)`
- `markProcessed()` sets `processed_at` to now

## Configuration

`config/webhook.php` (merged from `config.php` in this directory):
- `secrets.*` -- per-source signing secrets

## Integration

- Route registered via `ApiRoutesRegistering` lifecycle event (event-driven module loading pattern)
- Source parameter constrained to `[a-z0-9\-]+`
- Pair with `Core\Rules\SafeWebhookUrl` for outbound webhook URL validation
