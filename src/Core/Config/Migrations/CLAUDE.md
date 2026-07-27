# Config/Migrations/ — Config Schema Migrations

Database migrations for the hierarchical configuration system.

## Migrations

| File | Purpose |
|------|---------|
| `0001_01_01_000001_create_config_tables.php` | Creates core config tables: `config_keys`, `config_profiles`, `config_values`, `config_channels`, `config_resolved`. |
| `0001_01_01_000002_add_soft_deletes_to_config_profiles.php` | Adds soft delete support to `config_profiles`. |
| `0001_01_01_000003_add_is_sensitive_to_config_keys.php` | Adds `is_sensitive` flag for automatic encryption of values. |
| `0001_01_01_000004_create_config_versions_table.php` | Creates `config_versions` table for point-in-time snapshots and rollback. |

Uses early timestamps (`0001_01_01_*`) to run before application migrations.
