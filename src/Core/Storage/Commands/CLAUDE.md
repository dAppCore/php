# Storage/Commands/ — Storage Artisan Commands

## Commands

| Command | Signature | Purpose |
|---------|-----------|---------|
| `WarmCacheCommand` | `cache:warm` | Pre-populates cache with frequently accessed data. Options: `--stale` (only warm missing items), `--status` (show warming status), `--key=foo` (warm specific key). Prevents cold cache problems after deployments. |

Uses the `CacheWarmer` service which modules register their warmable items with.
