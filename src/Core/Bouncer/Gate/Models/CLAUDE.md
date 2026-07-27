# Bouncer/Gate/Models/ — Action Gate Models

## Models

| Model | Table | Purpose |
|-------|-------|---------|
| `ActionPermission` | `core_action_permissions` | Whitelisted action record. Stores action identifier, scope, guard, role, allowed flag, and training metadata (who trained it, when, from which route). Source: `trained`, `seeded`, or `manual`. |
| `ActionRequest` | `core_action_requests` | Audit log entry for all action permission checks. Records HTTP method, route, action, guard, user, IP, status (allowed/denied/pending), and whether training was triggered. |
