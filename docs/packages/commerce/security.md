---
title: Security
description: Security considerations and audit notes for core-commerce
updated: 2026-01-29
---

# Security Considerations

This document outlines security controls, known risks, and recommendations for the `core-commerce` package.

## Authentication & Authorisation

### API Authentication

| Endpoint Type | Authentication Method | Notes |
|--------------|----------------------|-------|
| Webhooks (`/api/webhooks/*`) | HMAC signature | Gateway-specific verification |
| Billing API (`/api/commerce/*`) | Laravel `auth` middleware | Session/Sanctum token |
| Provisioning API | Bearer token (planned) | Currently commented out |

### Webhook Security

Both payment gateways use HMAC signature verification:

**BTCPay:**
```php
// Signature in BTCPay-Sig header
$expectedSignature = hash_hmac('sha256', $payload, $webhookSecret);
hash_equals($expectedSignature, $providedSignature);
```

**Stripe:**
```php
// Uses Stripe SDK signature verification
\Stripe\Webhook::constructEvent($payload, $signature, $webhookSecret);
```

### Current Gaps

1. **No idempotency enforcement** - Webhook handlers check order state (`isPaid()`) but don't store processed event IDs. Replay attacks within the state-check window are possible.

2. **No IP allowlisting** - Webhook endpoints accept connections from any IP. Consider adding gateway IP ranges to allowlist.

3. **Rate limiting is global** - Current throttle (`120,1`) applies globally, not per-IP. A malicious actor could exhaust the limit.

## Data Protection

### Sensitive Data Handling

| Data Type | Storage | Protection |
|-----------|---------|------------|
| Card details | Never stored | Handled by gateways via redirect |
| Gateway API keys | Environment variables | Not in codebase |
| Webhook secrets | Environment variables | Used for HMAC |
| Tax IDs (VAT numbers) | Encrypted column recommended | Currently plain text |
| Billing addresses | Database JSON column | Consider encryption |

### PCI DSS Compliance

The commerce module is designed to be **PCI DSS SAQ A** compliant:

- No card data ever touches Host UK servers
- Checkout redirects to hosted payment pages (BTCPay/Stripe)
- Only tokenized references (customer IDs, payment method IDs) are stored
- No direct card number input in application

### GDPR Considerations

Personal data in commerce models:
- `orders.billing_name`, `billing_email`, `billing_address`
- `invoices.billing_*` fields
- `referrals.ip_address`, `user_agent`

**Recommendations:**
- Implement data export for billing history (right of access)
- Add retention policy for old orders/invoices
- Hash or truncate IP addresses after 90 days
- Document lawful basis for processing (contract performance)

## Input Validation

### Current Controls

```php
// Coupon codes normalized
$data['code'] = strtoupper($data['code']);

// Order totals calculated server-side
$taxResult = $this->taxService->calculateForOrderable($orderable, $taxableAmount);
$total = $subtotal - $discountAmount + $setupFee + $taxResult->taxAmount;

// Gateway responses logged without sensitive data
protected function sanitiseErrorMessage($response): string
```

### Validation Gaps

1. **Billing address structure** - Accepted as array without schema validation
2. **Coupon code length** - No maximum length enforcement
3. **Metadata fields** - JSON columns accept arbitrary structure

### Recommendations

```php
// Add validation rules
$rules = [
    'billing_address.line1' => ['required', 'string', 'max:255'],
    'billing_address.city' => ['required', 'string', 'max:100'],
    'billing_address.country' => ['required', 'string', 'size:2'],
    'billing_address.postal_code' => ['required', 'string', 'max:20'],
    'coupon_code' => ['nullable', 'string', 'max:32', 'alpha_dash'],
];
```

## Transaction Security

### Idempotency

Order creation supports idempotency keys:

```php
if ($idempotencyKey) {
    $existingOrder = Order::where('idempotency_key', $idempotencyKey)->first();
    if ($existingOrder) {
        return $existingOrder;
    }
}
```

**Gap:** Webhooks don't use idempotency. Add `WebhookEvent` lookup:

```php
if (WebhookEvent::where('idempotency_key', $event['id'])->exists()) {
    return response('Already processed', 200);
}
```

### Race Conditions

**Identified risks:**

1. **Concurrent subscription operations** - Pause/unpause/cancel without locks
2. **Coupon redemption** - `incrementUsage()` without atomic check
3. **Payout requests** - Commission assignment without row locks

**Mitigation:** Add `FOR UPDATE` locks or use atomic operations:

```php
// Use DB::transaction with locking
$commission = ReferralCommission::lockForUpdate()
    ->where('id', $commissionId)
    ->where('status', 'matured')
    ->first();
```

### Amount Verification

**Current state:** BTCPay webhook trusts order total without verifying against gateway response.

**Risk:** Under/overpayment handling undefined.

**Recommendation:**
```php
$settledAmount = $invoiceData['raw']['amount'] ?? null;
if ($settledAmount !== null && abs($settledAmount - $order->total) > 0.01) {
    Log::warning('Payment amount mismatch', [
        'order_total' => $order->total,
        'settled_amount' => $settledAmount,
    ]);
    // Handle partial payment or overpayment
}
```

## Fraud Prevention

### Current Controls

- Checkout session TTL (30 minutes default)
- Rate limiting on API endpoints
- Idempotency keys for order creation

### Missing Controls

1. **Velocity checks** - No detection of rapid-fire order attempts
2. **Geo-blocking** - No IP geolocation validation against billing country
3. **Card testing detection** - No small-amount charge pattern detection
4. **Device fingerprinting** - No device/browser tracking

### Recommendations

```php
// Add CheckoutRateLimiter to createCheckout
$rateLimiter = app(CheckoutRateLimiter::class);
if (!$rateLimiter->attempt($workspace->id)) {
    throw new TooManyCheckoutAttemptsException();
}

// Consider Stripe Radar for card payments
'stripe' => [
    'radar_enabled' => true,
    'block_threshold' => 75, // Block if risk score > 75
],
```

## Audit Logging

### What's Logged

- Order status changes via `LogsActivity` trait
- Subscription status changes via `LogsActivity` trait
- Webhook events via `WebhookLogger` service
- Payment failures and retries

### What's Not Logged

- Failed authentication attempts on billing API
- Coupon validation failures
- Tax ID validation API calls
- Admin actions on refunds/credit notes

### Recommendations

Add audit events for:
```php
// Sensitive operations
activity('commerce')
    ->causedBy($admin)
    ->performedOn($refund)
    ->withProperties(['reason' => $reason])
    ->log('Refund processed');
```

## Secrets Management

### Environment Variables

```bash
# Gateway credentials
BTCPAY_URL=https://pay.host.uk.com
BTCPAY_STORE_ID=xxx
BTCPAY_API_KEY=xxx
BTCPAY_WEBHOOK_SECRET=xxx

STRIPE_KEY=pk_xxx
STRIPE_SECRET=sk_xxx
STRIPE_WEBHOOK_SECRET=whsec_xxx

# Tax API credentials
COMMERCE_EXCHANGE_RATE_API_KEY=xxx
```

### Key Rotation

No automated key rotation currently implemented.

**Recommendations:**
- Store credentials in secrets manager (AWS Secrets Manager, HashiCorp Vault)
- Implement webhook secret rotation with grace period
- Alert on API key exposure in logs

## Security Checklist

### Before Production

- [ ] Webhook secrets are unique per environment
- [ ] Rate limiting tuned for expected traffic
- [ ] Error messages don't leak internal details
- [ ] API keys not in version control
- [ ] SSL/TLS required for all endpoints

### Ongoing

- [ ] Monitor webhook failure rates
- [ ] Review failed payment patterns weekly
- [ ] Audit refund activity monthly
- [ ] Update gateway SDKs quarterly
- [ ] Penetration test annually

## Incident Response

### Compromised API Key

1. Revoke key immediately in gateway dashboard
2. Generate new key
3. Update environment variable
4. Restart application
5. Audit recent transactions for anomalies

### Webhook Secret Leaked

1. Generate new secret in gateway
2. Update both old and new in config (grace period)
3. Monitor for invalid signature attempts
4. Remove old secret after 24 hours

### Suspected Fraud

1. Pause affected subscription
2. Flag orders for manual review
3. Contact gateway for chargeback advice
4. Document in incident log

## Third-Party Dependencies

### Gateway SDKs

| Package | Version | Security Notes |
|---------|---------|----------------|
| `stripe/stripe-php` | ^12.0 | Keep updated for security patches |

### Other Dependencies

- `spatie/laravel-activitylog` - Audit logging
- `barryvdh/laravel-dompdf` - PDF generation (ensure no user input in HTML)

### Dependency Audit

Run regularly:
```bash
composer audit
```

## Contact

Report security issues to: security@host.uk.com

Do not open public issues for security vulnerabilities.
