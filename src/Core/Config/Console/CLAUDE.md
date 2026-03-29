# Config/Console/ — Config Artisan Commands

Artisan commands for managing the hierarchical configuration system.

## Commands

| Command | Signature | Purpose |
|---------|-----------|---------|
| `ConfigListCommand` | `config:list` | List config keys with resolved values. Filters by workspace, category, or configured-only. |
| `ConfigPrimeCommand` | `config:prime` | Materialise resolved config into the fast-read table. Primes system and/or specific workspace. |
| `ConfigExportCommand` | `config:export` | Export config to JSON or YAML file. Supports workspace scope and sensitive value inclusion. |
| `ConfigImportCommand` | `config:import` | Import config from JSON or YAML file. Supports dry-run mode and automatic version snapshots. |
| `ConfigVersionCommand` | `config:version` | Manage config versions — list, create snapshots, show, rollback, and compare versions. |
