# Config/Database/Seeders/ — Config Key Seeders

## Files

| File | Purpose |
|------|---------|
| `ConfigKeySeeder.php` | Seeds known configuration keys into the `config_keys` table. Defines CDN (Bunny), storage (Hetzner S3), social, analytics, and bio settings with their types, categories, and defaults. Uses `firstOrCreate` for idempotency. |

Part of the Config subsystem's M1 layer (key definitions). These keys are then assigned values via `ConfigValue` at system or workspace scope.
