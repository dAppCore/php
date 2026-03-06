---
title: Webhooks
description: Payment gateway webhook handling documentation
updated: 2026-01-29
---

# Webhook Handling

This document describes how payment gateway webhooks are processed in the commerce module.

## Overview

Payment gateways notify the application of payment events via webhooks. These are HTTP POST requests sent to predefined endpoints when payment state changes.

```
┌──────────────┐          ┌──────────────┐          ┌──────────────┐
│   BTCPay     │          │   Host UK    │          │   Stripe     │
│   Server     │          │   Commerce   │          │   API        │
└──────┬───────┘          └──────┬───────┘          └──────┬───────┘
       │                         │                         │
       │ POST /api/webhooks/     │                         │
       │      btcpay             │                         │
       │ ───────────────────────▶│                         │
       │                         │                         │
       │                         │ POST /api/webhooks/     │
       │                         │      stripe             │
       │                         │◀─────────────────────────
       │                         │                         │
```

## Endpoints

| Gateway | Endpoint | Signature Header |
|---------|----------|------------------|
| BTCPay | `POST /api/webhooks/btcpay` | `BTCPay-Sig` |
| Stripe | `POST /api/webhooks/stripe` | `Stripe-Signature` |

Both endpoints:
- Rate limited: 120 requests per minute
- No authentication middleware (signature verification only)
- Return 200 for successful processing (even if event is skipped)
- Return 401 for invalid signatures
- Return 500 for processing errors (triggers gateway retry)

## BTCPay Webhooks

### Configuration

In BTCPay Server dashboard:
1. Navigate to Store Settings > Webhooks
2. Create webhook with URL: `https://yourdomain.com/api/webhooks/btcpay`
3. Select events to send
4. Copy webhook secret to `BTCPAY_WEBHOOK_SECRET`

### Event Types

| BTCPay Event | Mapped Type | Action |
|--------------|-------------|--------|
| `InvoiceCreated` | `invoice.created` | No action |
| `InvoiceReceivedPayment` | `invoice.payment_received` | Order → processing |
| `InvoiceProcessing` | `invoice.processing` | Order → processing |
| `InvoiceSettled` | `invoice.paid` | Fulfil order |
| `InvoiceExpired` | `invoice.expired` | Order → failed |
| `InvoiceInvalid` | `invoice.failed` | Order → failed |

### Processing Flow

```php
// BTCPayWebhookController::handle()

1. Verify signature
   └── 401 if invalid

2. Parse event
   └── Extract type, invoice ID, metadata

3. Log webhook event
   └── WebhookLogger creates audit record

4. Route to handler (in transaction)
   ├── invoice.paid → handleSettled()
   ├── invoice.expired → handleExpired()
   └── default → handleUnknownEvent()

5. Return response
   └── 200 OK (even for skipped events)
```

### Invoice Settlement Handler

```php
protected function handleSettled(array $event): Response
{
    // 1. Find order by gateway session ID
    $order = Order::where('gateway', 'btcpay')
        ->where('gateway_session_id', $event['id'])
        ->first();

    // 2. Skip if already paid (idempotency)
    if ($order->isPaid()) {
        return response('Already processed', 200);
    }

    // 3. Create payment record
    $payment = Payment::create([
        'gateway' => 'btcpay',
        'gateway_payment_id' => $event['id'],
        'amount' => $order->total,
        'status' => 'succeeded',
        // ...
    ]);

    // 4. Fulfil order (provisions entitlements, creates invoice)
    $this->commerce->fulfillOrder($order, $payment);

    // 5. Send confirmation email
    $this->sendOrderConfirmation($order);

    return response('OK', 200);
}
```

## Stripe Webhooks

### Configuration

In Stripe Dashboard:
1. Navigate to Developers > Webhooks
2. Add endpoint: `https://yourdomain.com/api/webhooks/stripe`
3. Select events to listen for
4. Copy signing secret to `STRIPE_WEBHOOK_SECRET`

### Event Types

| Stripe Event | Action |
|--------------|--------|
| `checkout.session.completed` | Fulfil order, create subscription |
| `invoice.paid` | Renew subscription period |
| `invoice.payment_failed` | Mark past_due, trigger dunning |
| `customer.subscription.created` | Fallback (usually handled by checkout) |
| `customer.subscription.updated` | Sync status, period dates |
| `customer.subscription.deleted` | Cancel, revoke entitlements |
| `payment_method.attached` | Store payment method |
| `payment_method.detached` | Deactivate payment method |
| `payment_method.updated` | Update card details |
| `setup_intent.succeeded` | Attach payment method from setup flow |

### Checkout Completion Handler

```php
protected function handleCheckoutCompleted(array $event): Response
{
    $session = $event['raw']['data']['object'];
    $orderId = $session['metadata']['order_id'];

    // Find and validate order
    $order = Order::find($orderId);
    if (!$order || $order->isPaid()) {
        return response('Already processed', 200);
    }

    // Create payment record
    $payment = Payment::create([
        'gateway' => 'stripe',
        'gateway_payment_id' => $session['payment_intent'],
        'amount' => $session['amount_total'] / 100,
        'status' => 'succeeded',
    ]);

    // Handle subscription if present
    if (!empty($session['subscription'])) {
        $this->createOrUpdateSubscriptionFromSession($order, $session);
    }

    // Fulfil order
    $this->commerce->fulfillOrder($order, $payment);

    return response('OK', 200);
}
```

### Subscription Invoice Handler

```php
protected function handleInvoicePaid(array $event): Response
{
    $invoice = $event['raw']['data']['object'];
    $subscriptionId = $invoice['subscription'];

    // Find subscription
    $subscription = Subscription::where('gateway', 'stripe')
        ->where('gateway_subscription_id', $subscriptionId)
        ->first();

    // Update period dates
    $subscription->renew(
        Carbon::createFromTimestamp($invoice['period_start']),
        Carbon::createFromTimestamp($invoice['period_end'])
    );

    // Create payment record
    $payment = Payment::create([...]);

    // Create local invoice
    $this->invoiceService->createForRenewal($subscription->workspace, ...);

    return response('OK', 200);
}
```

## Signature Verification

### BTCPay

```php
// BTCPayGateway::verifyWebhookSignature()

$providedSignature = $signature;
if (str_starts_with($signature, 'sha256=')) {
    $providedSignature = substr($signature, 7);
}

$expectedSignature = hash_hmac('sha256', $payload, $this->webhookSecret);

return hash_equals($expectedSignature, $providedSignature);
```

### Stripe

```php
// StripeGateway::verifyWebhookSignature()

try {
    \Stripe\Webhook::constructEvent($payload, $signature, $this->webhookSecret);
    return true;
} catch (\Exception $e) {
    return false;
}
```

## Webhook Logging

All webhook events are logged via `WebhookLogger`:

```php
// Start logging
$this->webhookLogger->startFromParsedEvent('btcpay', $event, $payload, $request);

// Link to entities for audit trail
$this->webhookLogger->linkOrder($order);
$this->webhookLogger->linkSubscription($subscription);

// Mark outcome
$this->webhookLogger->success($response);
$this->webhookLogger->fail($errorMessage, $statusCode);
$this->webhookLogger->skip($reason);
```

Logged data includes:
- Event type and ID
- Raw payload (encrypted)
- IP address and user agent
- Processing outcome
- Related order/subscription IDs

## Error Handling

### Gateway Retries

Both gateways retry failed webhooks:

| Gateway | Retry Schedule | Max Attempts |
|---------|---------------|--------------|
| BTCPay | Exponential backoff | Configurable |
| Stripe | Exponential over 3 days | ~20 attempts |

**Important:** Return `200 OK` even for events that are skipped or already processed. Only return `500` for actual processing errors that should be retried.

### Transaction Safety

All webhook handlers wrap processing in database transactions:

```php
try {
    $response = DB::transaction(function () use ($event) {
        return match ($event['type']) {
            'invoice.paid' => $this->handleSettled($event),
            // ...
        };
    });
    return $response;
} catch (\Exception $e) {
    Log::error('Webhook processing error', [...]);
    return response('Processing error', 500);
}
```

## Testing Webhooks

### Local Development

Use gateway CLI tools to send test webhooks:

**BTCPay:**
```bash
# Trigger test webhook from BTCPay admin
# Or use btcpay-cli if available
```

**Stripe:**
```bash
# Forward webhooks to local
stripe listen --forward-to localhost:8000/api/webhooks/stripe

# Trigger specific event
stripe trigger checkout.session.completed
```

### Automated Tests

See `tests/Feature/WebhookTest.php` for webhook handler tests:

```php
test('btcpay settled webhook fulfils order', function () {
    $order = Order::factory()->create(['status' => 'processing']);

    $payload = json_encode([
        'type' => 'InvoiceSettled',
        'invoiceId' => $order->gateway_session_id,
        // ...
    ]);

    $signature = hash_hmac('sha256', $payload, config('commerce.gateways.btcpay.webhook_secret'));

    $response = $this->postJson('/api/webhooks/btcpay', [], [
        'BTCPay-Sig' => $signature,
        'Content-Type' => 'application/json',
    ]);

    $response->assertStatus(200);
    expect($order->fresh()->status)->toBe('paid');
});
```

## Troubleshooting

### Common Issues

**401 Invalid Signature**
- Check webhook secret matches environment variable
- Ensure raw payload is used (not parsed JSON)
- Verify signature header name is correct

**Order Not Found**
- Check `gateway_session_id` matches invoice ID
- Verify order was created before webhook arrived
- Check for typos in metadata passed to gateway

**Duplicate Processing**
- Normal behavior if webhook is retried
- Order state check (`isPaid()`) prevents double fulfillment
- Consider adding idempotency key storage

### Debug Logging

Enable verbose logging temporarily:

```php
// In webhook controller
Log::debug('Webhook payload', [
    'type' => $event['type'],
    'id' => $event['id'],
    'raw' => $event['raw'],
]);
```

### Webhook Event Viewer

Query logged events:

```sql
SELECT * FROM commerce_webhook_events
WHERE event_type = 'InvoiceSettled'
  AND status = 'failed'
ORDER BY created_at DESC
LIMIT 10;
```
