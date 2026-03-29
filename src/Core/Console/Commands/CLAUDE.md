# Console/Commands/ — Core Framework Commands

Artisan commands for framework scaffolding and maintenance.

## Commands

| Command | Signature | Purpose |
|---------|-----------|---------|
| `InstallCommand` | `core:install` | Framework installation wizard — sets up sensible defaults for new projects. |
| `MakeModCommand` | `core:make-mod` | Generates a new module scaffold in the `Mod` namespace with Boot.php event-driven loading pattern. |
| `MakePlugCommand` | `core:make-plug` | Generates a new plugin scaffold in the `Plug` namespace. |
| `MakeWebsiteCommand` | `core:make-website` | Generates a new website module scaffold in the `Website` namespace. |
| `NewProjectCommand` | `core:new` | Creates a complete new project from the Core PHP template. |
| `PruneEmailShieldStatsCommand` | `emailshield:prune` | Prunes old EmailShield validation statistics. |
| `ScheduleSyncCommand` | `core:schedule-sync` | Synchronises scheduled tasks across the application. |
