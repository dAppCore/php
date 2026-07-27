# Bouncer/Gate/ — Action Gate Authorisation

Whitelist-based request authorisation system. Philosophy: "If it wasn't trained, it doesn't exist."

## Files

| File | Purpose |
|------|---------|
| `Boot.php` | ServiceProvider — registers middleware, configures action gate. |
| `ActionGateMiddleware.php` | Intercepts requests, checks if the target action is permitted. Production mode blocks unknown actions (403). Training mode prompts for approval. |
| `ActionGateService.php` | Core service — resolves action names from routes/controllers, checks `ActionPermission` records. Supports `#[Action]` attribute, auto-resolution from controller names, and training mode. |
| `RouteActionMacro.php` | Adds `->action('name')` and `->bypassGate()` macros to Laravel routes for fluent action naming. |

## Integration Flow

```
Request -> ActionGateMiddleware -> ActionGateService::check() -> ActionPermission (allowed/denied) -> Controller
```
