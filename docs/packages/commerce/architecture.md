---
title: Architecture
description: Technical architecture of the core-commerce package
updated: 2026-01-29
---

# Commerce Architecture

This document describes the technical architecture of the `core-commerce` package, which provides billing, subscriptions, and payment processing for the Host UK platform.

## Overview

The commerce module implements a multi-gateway payment system supporting cryptocurrency (BTCPay) and traditional card payments (Stripe). It handles the complete commerce lifecycle from checkout to recurring billing, dunning, and refunds.

```
┌─────────────────────────────────────────────────────────────────┐
│                        Commerce Module                          │
├─────────────────────────────────────────────────────────────────┤
│  Services Layer                                                 │
│  ┌─────────────┐ ┌──────────────┐ ┌───────────────┐            │
│  │ Commerce    │ │ Subscription │ │ Dunning       │            │
│  │ Service     │ │ Service      │ │ Service       │            │
│  └─────────────┘ └──────────────┘ └───────────────┘            │
│  ┌─────────────┐ ┌──────────────┐ ┌───────────────┐            │
│  │ Invoice     │ │ Coupon       │ │ Tax           │            │
│  │ Service     │ │ Service      │ │ Service       │            │
│  └─────────────┘ └──────────────┘ └───────────────┘            │
├─────────────────────────────────────────────────────────────────┤
│  Gateway Layer                                                  │
│  ┌──────────────────────┐  ┌──────────────────────┐            │
│  │ BTCPayGateway        │  │ StripeGateway        │            │
│  │ (Primary)            │  │ (Secondary)          │            │
│  └──────────────────────┘  └──────────────────────┘            │
│              │                          │                       │
│              └────────────┬─────────────┘                       │
│                           │                                     │
│              ┌────────────▼─────────────┐                       │
│              │ PaymentGatewayContract   │                       │
│              └──────────────────────────┘                       │
└─────────────────────────────────────────────────────────────────┘
```

## Core Concepts

### Orderable Interface

The commerce system uses polymorphic relationships via the `Orderable` contract. Both `Workspace` and `User` models can place orders, enabling:

- **Workspace orders**: Subscription packages, team features
- **User orders**: Individual boosts, one-time purchases

```php
interface Orderable
{
    public function getBillingName(): string;
    public function getBillingEmail(): string;
    public function getBillingAddress(): array;
    public function getTaxCountry(): ?string;
}
```

### Order Lifecycle

```
┌──────────┐    ┌────────────┐    ┌──────────┐    ┌────────┐
│ pending  │───▶│ processing │───▶│   paid   │───▶│refunded│
└──────────┘    └────────────┘    └──────────┘    └────────┘
     │               │
     │               │
     ▼               ▼
┌──────────┐    ┌──────────┐
│cancelled │    │  failed  │
└──────────┘    └──────────┘
```

1. **pending**: Order created, awaiting checkout
2. **processing**: Customer redirected to payment gateway
3. **paid**: Payment confirmed, entitlements provisioned
4. **failed**: Payment declined or expired
5. **cancelled**: Customer abandoned checkout
6. **refunded**: Full refund processed

### Subscription States

```
┌────────┐    ┌──────────┐    ┌────────┐    ┌───────────┐
│ active │───▶│ past_due │───▶│ paused │───▶│ cancelled │
└────────┘    └──────────┘    └────────┘    └───────────┘
     │              │              │
     ▼              │              │
┌──────────┐        │              │
│ trialing │────────┘              │
└──────────┘                       │
     │                             │
     └─────────────────────────────┘
```

- **active**: Subscription in good standing
- **trialing**: Within trial period (no payment required)
- **past_due**: Payment failed, within retry window
- **paused**: Billing paused (dunning or user-initiated)
- **cancelled**: Subscription ended

## Service Layer

### CommerceService

Main orchestration service. Coordinates order creation, checkout, and fulfillment.

```php
// Create an order
$order = $commerce->createOrder($workspace, $package, 'monthly', $coupon);

// Create checkout session (redirects to gateway)
$checkout = $commerce->createCheckout($order, 'btcpay', $successUrl, $cancelUrl);

// Fulfill order after payment (called by webhook)
$commerce->fulfillOrder($order, $payment);
```

Key responsibilities:
- Gateway selection and initialization
- Customer management across gateways
- Order-to-entitlement provisioning
- Currency formatting and conversion

### SubscriptionService

Manages subscription lifecycle without gateway interaction.

```php
// Create local subscription record
$subscription = $subscriptions->create($workspacePackage, 'monthly');

// Handle plan changes with proration
$result = $subscriptions->changePlan($subscription, $newPackage, prorate: true);

// Pause/unpause with limits
$subscriptions->pause($subscription);
$subscriptions->unpause($subscription);
```

Proration calculation:
```
creditAmount = currentPrice * (daysRemaining / totalPeriodDays)
proratedNewCost = newPrice * (daysRemaining / totalPeriodDays)
netAmount = proratedNewCost - creditAmount
```

### DunningService

Handles failed payment recovery with exponential backoff.

```
Day 0:  Payment fails → subscription marked past_due
Day 1:  First retry
Day 3:  Second retry
Day 7:  Third retry → subscription paused
Day 14: Workspace suspended (features restricted)
Day 30: Subscription cancelled
```

Configuration in `config.php`:
```php
'dunning' => [
    'retry_days' => [1, 3, 7],
    'suspend_after_days' => 14,
    'cancel_after_days' => 30,
    'initial_grace_hours' => 24,
],
```

### TaxService

Jurisdiction-based tax calculation supporting:
- UK VAT (20%)
- EU VAT via VIES validation
- US state sales tax (nexus-based)
- Australian GST (10%)

B2B reverse charge is applied automatically when a valid VAT number is provided for EU customers.

```php
$taxResult = $taxService->calculate($workspace, $amount);
// Returns: TaxResult with taxAmount, taxRate, jurisdiction, isExempt
```

## Payment Gateways

### PaymentGatewayContract

All gateways implement this interface ensuring consistent behavior:

```php
interface PaymentGatewayContract
{
    // Identity
    public function getIdentifier(): string;
    public function isEnabled(): bool;

    // Customer management
    public function createCustomer(Workspace $workspace): string;

    // Checkout
    public function createCheckoutSession(Order $order, ...): array;
    public function getCheckoutSession(string $sessionId): array;

    // Payments
    public function charge(Workspace $workspace, int $amountCents, ...): Payment;
    public function chargePaymentMethod(PaymentMethod $pm, ...): Payment;

    // Subscriptions
    public function createSubscription(Workspace $workspace, ...): Subscription;
    public function cancelSubscription(Subscription $sub, bool $immediately): void;

    // Webhooks
    public function verifyWebhookSignature(string $payload, string $sig): bool;
    public function parseWebhookEvent(string $payload): array;
}
```

### BTCPayGateway (Primary)

Cryptocurrency payment gateway supporting BTC, LTC, XMR.

**Characteristics:**
- No saved payment methods (each payment is unique)
- No automatic recurring billing (requires customer action)
- Invoice-based workflow with expiry
- HMAC signature verification for webhooks

**Webhook Events:**
- `InvoiceCreated` → No action
- `InvoiceReceivedPayment` → Order status: processing
- `InvoiceProcessing` → Waiting for confirmations
- `InvoiceSettled` → Fulfill order
- `InvoiceExpired` → Mark order failed

### StripeGateway (Secondary)

Traditional card payment gateway.

**Characteristics:**
- Saved payment methods for recurring
- Automatic subscription billing
- Setup intents for card-on-file
- Stripe Customer Portal integration

**Webhook Events:**
- `checkout.session.completed` → Fulfill order
- `invoice.paid` → Renew subscription
- `invoice.payment_failed` → Trigger dunning
- `customer.subscription.deleted` → Revoke entitlements

## Data Models

### Entity Relationship

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│  Workspace  │────▶│   Order     │────▶│  OrderItem  │
└─────────────┘     └─────────────┘     └─────────────┘
       │                   │
       │                   ▼
       │            ┌─────────────┐     ┌─────────────┐
       │            │   Invoice   │────▶│InvoiceItem  │
       │            └─────────────┘     └─────────────┘
       │                   │
       │                   ▼
       │            ┌─────────────┐     ┌─────────────┐
       └───────────▶│   Payment   │────▶│   Refund    │
                    └─────────────┘     └─────────────┘
                           │
                           ▼
┌─────────────┐     ┌─────────────┐
│   Coupon    │────▶│ CouponUsage │
└─────────────┘     └─────────────┘
```

### Multi-Entity Commerce (M1/M2/M3)

The commerce module supports a hierarchical entity structure:

- **M1 (Master Company)**: Source of truth, owns product catalog
- **M2 (Facade/Storefront)**: Selects from M1 catalog, can override content
- **M3 (Dropshipper)**: Full inheritance, no management responsibility

```
          ┌─────────┐
          │   M1    │ ← Product catalog owner
          └────┬────┘
               │
      ┌────────┴────────┐
      │                 │
┌─────▼─────┐     ┌─────▼─────┐
│    M2     │     │    M2     │ ← Storefronts
└─────┬─────┘     └───────────┘
      │
┌─────▼─────┐
│    M3     │ ← Dropshipper
└───────────┘
```

Permission matrix controls which operations each entity type can perform, with a "training mode" for undefined permissions.

## Event System

### Domain Events

```php
// Dispatched automatically on model changes
SubscriptionCreated::class  → RewardAgentReferralOnSubscription
SubscriptionRenewed::class  → ResetUsageOnRenewal
OrderPaid::class            → CreateReferralCommission
```

### Listeners

- `ProvisionSocialHostSubscription`: Product-specific provisioning logic
- `RewardAgentReferralOnSubscription`: Attribute referral for new subscriptions
- `ResetUsageOnRenewal`: Clear usage counters on billing period reset
- `CreateReferralCommission`: Calculate affiliate commission on paid orders

## Directory Structure

```
core-commerce/
├── Boot.php                 # ServiceProvider, event registration
├── config.php               # All configuration (currencies, gateways, tax)
├── Concerns/                # Traits for models
├── Console/                 # Artisan commands (dunning, reminders)
├── Contracts/               # Interfaces (Orderable)
├── Controllers/             # HTTP controllers
│   ├── Api/                 # REST API endpoints
│   └── Webhooks/            # Gateway webhook handlers
├── Data/                    # DTOs and value objects
├── Events/                  # Domain events
├── Exceptions/              # Custom exceptions
├── Jobs/                    # Queue jobs
├── Lang/                    # Translations
├── Listeners/               # Event listeners
├── Mail/                    # Mailable classes
├── Mcp/                     # MCP tool handlers
├── Middleware/              # HTTP middleware
├── Migrations/              # Database migrations
├── Models/                  # Eloquent models
├── Notifications/           # Laravel notifications
├── routes/                  # Route definitions
├── Services/                # Business logic layer
│   └── PaymentGateway/      # Gateway implementations
├── tests/                   # Pest tests
└── View/                    # Blade templates and Livewire components
    ├── Blade/               # Blade templates
    └── Modal/               # Livewire components (Admin/Web)
```

## Configuration

All commerce configuration lives in `config.php`:

```php
return [
    'currency' => 'GBP',           // Default currency
    'currencies' => [...],          // Supported currencies, exchange rates
    'gateways' => [
        'btcpay' => [...],          // Primary gateway
        'stripe' => [...],          // Secondary gateway
    ],
    'billing' => [...],             // Invoice prefixes, due days
    'dunning' => [...],             // Retry schedule, suspension timing
    'tax' => [...],                 // Tax rates, VAT validation
    'subscriptions' => [...],       // Proration, pause limits
    'checkout' => [...],            // Session TTL, country restrictions
    'features' => [...],            // Toggle coupons, refunds, trials
    'usage_billing' => [...],       // Metered billing settings
    'matrix' => [...],              // M1/M2/M3 permission matrix
];
```

## Testing

Tests use Pest with `RefreshDatabase` trait:

```bash
# Run all tests
composer test

# Run specific test file
vendor/bin/pest tests/Feature/CheckoutFlowTest.php

# Run tests matching pattern
vendor/bin/pest --filter="proration"
```

Test categories:
- `CheckoutFlowTest`: End-to-end order flow
- `SubscriptionServiceTest`: Subscription lifecycle, proration
- `DunningServiceTest`: Payment recovery flows
- `WebhookTest`: Gateway webhook handling
- `TaxServiceTest`: Tax calculation, VAT validation
- `CouponServiceTest`: Discount application
- `RefundServiceTest`: Refund processing
