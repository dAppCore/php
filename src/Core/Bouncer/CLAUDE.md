# Bouncer

Early-exit security middleware + whitelist-based action authorisation gate.

## What It Does

Two subsystems in one:

1. **Bouncer** (top-level): IP blocklist + SEO redirects, runs before all other middleware
2. **Gate** (subdirectory): Whitelist-based controller action authorisation with training mode

## Bouncer (IP Blocking + Redirects)

### Key Classes

| Class | Purpose |
|-------|---------|
| `Boot` | ServiceProvider registering `BlocklistService`, `RedirectService`, and migrations |
| `BouncerMiddleware` | Early-exit middleware: sets trusted proxies, checks blocklist (O(1) via cached set), handles SEO redirects, then passes through |
| `BlocklistService` | IP blocking with Redis-cached lookup. Statuses: `pending` (honeypot, needs review), `approved` (active block), `rejected` (reviewed, not blocked). Methods: `isBlocked()`, `block()`, `unblock()`, `syncFromHoneypot()`, `approve()`, `reject()`, `getPending()`, `getStats()` |
| `RedirectService` | Cached SEO redirects from `seo_redirects` table. Supports exact match and wildcard (`path/*`). Methods: `match()`, `add()`, `remove()` |

### Hidden Ideas

- Blocked IPs get `418 I'm a teapot` with `X-Powered-By: Earl Grey`
- Honeypot monitors paths from `robots.txt` disallow list; critical paths (`/admin`, `/.env`, `/wp-admin`) trigger auto-block
- Rate-limited honeypot logging prevents DoS via log flooding
- `TRUSTED_PROXIES` env var: comma-separated IPs or `*` (trust all)

## Gate (Action Whitelist)

Philosophy: **"If it wasn't trained, it doesn't exist."**

### Key Classes

| Class | Purpose |
|-------|---------|
| `Gate\Boot` | ServiceProvider registering middleware, migrations, route macros, and training routes |
| `ActionGateService` | Resolves action name from route (3-level priority), checks against `ActionPermission` table, logs to `ActionRequest`. Methods: `check()`, `allow()`, `deny()`, `resolveAction()` |
| `ActionGateMiddleware` | Enforces gate: allowed = pass, denied = 403, training = approval prompt (JSON for API, redirect for web) |
| `Action` (attribute) | `#[Action('product.create', scope: 'product')]` on controller methods |
| `ActionPermission` (model) | Whitelist record: action + guard + role + scope. Methods: `isAllowed()`, `train()`, `revoke()`, `allowedFor()` |
| `ActionRequest` (model) | Audit log of all permission checks. Methods: `log()`, `pending()`, `deniedActionsSummary()`, `prune()` |
| `RouteActionMacro` | Adds `->action('name')`, `->bypassGate()`, `->requiresTraining()` to Route |

### Action Resolution Priority

1. Route action: `Route::post(...)->action('product.create')`
2. Controller attribute: `#[Action('product.create')]`
3. Auto-resolved: `ProductController@store` becomes `product.store`

### Training Mode

When `core.bouncer.training_mode = true`, unknown actions prompt for approval instead of blocking. Training routes at `/_bouncer/approve` and `/_bouncer/pending`.

## Integration

- BouncerMiddleware runs FIRST in the stack (replaces Laravel TrustProxies)
- ActionGateMiddleware appends to `web`, `admin`, `api`, `client` groups
- Config: `core.bouncer.enabled`, `core.bouncer.training_mode`, `core.bouncer.guarded_middleware`
- DB tables: `blocked_ips`, `seo_redirects`, `honeypot_hits`, `core_action_permissions`, `core_action_requests`
