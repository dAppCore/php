# Core/Front/Client

SaaS customer dashboard for namespace owners.

## Concept

```
Core/Front/Web    → Public (anonymous, read-only)
Core/Front/Client → SaaS customer (authenticated, namespace owner)  ← THIS
Core/Front/Admin  → Backend admin (privileged)
Core/Hub          → SaaS operator (Host.uk.com control plane)
```

## Namespace vs Workspace

- **Namespace** = your identity, tied to a URI/handle (lt.hn/you, you.lthn)
- **Workspace** = management container (org/agency that can own multiple namespaces)
- **Personal workspace** = IS your namespace (1:1 for solo users)

A user with just a personal workspace uses **Client** to manage their namespace.
An org workspace with multiple namespaces uses **Hub** for team management.

## Use Cases

- Bio page editor (lt.hn/you)
- Analytics dashboard (your stats)
- Domain management (custom domains, web3)
- Settings (profile, notifications)
- Boost purchases (expand namespace entitlements)

## Not For

- Team/org management (use Hub)
- Multi-namespace management (use Hub)
- Backend admin tasks (use Admin)
- Public viewing (use Web)

## Middleware

```php
Route::middleware('client')->group(function () {
    // Namespace owner routes
});
```

## Views

```blade
@extends('client::layouts.app')

<x-client::component />
```
