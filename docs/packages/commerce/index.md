---
title: Commerce Package
navTitle: Commerce
navOrder: 30
---

# Commerce Package

Billing, subscriptions, and payment processing for the Host UK platform.

## Overview

The commerce module implements a multi-gateway payment system supporting cryptocurrency (BTCPay) and traditional card payments (Stripe). It handles the complete commerce lifecycle from checkout to recurring billing, dunning, and refunds.

## Features

- **Multi-gateway payments** - BTCPay (primary) and Stripe (secondary)
- **Subscriptions** - Recurring billing with plan management
- **Invoicing** - Automatic invoice generation and delivery
- **Dunning** - Failed payment recovery workflows
- **Coupons** - Discount codes and promotional pricing
- **Tax handling** - VAT/GST calculation and compliance

## Installation

```bash
composer require host-uk/core-commerce
```

## Documentation

- [Architecture](./architecture) - Technical architecture and design
- [Security](./security) - Payment security and PCI compliance
- [Webhooks](./webhooks) - Payment gateway webhook handling