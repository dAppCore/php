# Core\Console

Framework artisan commands registered via the `ConsoleBooting` lifecycle event.

## Boot

Uses the event-driven module loading pattern:

```php
public static array $listens = [
    ConsoleBooting::class => 'onConsole',
];
```

## Commands

| Command | Signature | Purpose |
|---------|-----------|---------|
| `InstallCommand` | `core:install` | Framework setup wizard: env file, app config, migrations, app key, storage link. Supports `--dry-run` and `--force` |
| `NewProjectCommand` | `core:new` | Scaffold a new project |
| `MakeModCommand` | `make:mod {name}` | Generate a module in the `Mod` namespace with Boot.php. Flags: `--web`, `--admin`, `--api`, `--console`, `--all` |
| `MakePlugCommand` | `make:plug` | Generate a plugin scaffold |
| `MakeWebsiteCommand` | `make:website` | Generate a Website module scaffold |
| `PruneEmailShieldStatsCommand` | prunes `email_shield_stats` | Cleans old EmailShield validation stats |
| `ScheduleSyncCommand` | schedule sync | Schedule synchronisation |

## Conventions

- All commands use `declare(strict_types=1)` and the `Core\Console\Commands` namespace.
- `MakeModCommand` generates a complete module scaffold with optional handler stubs (web routes, admin panel, API, console).
- `InstallCommand` tracks progress via named installation steps and supports dry-run mode.
- Commands are registered via `$event->command()` on the `ConsoleBooting` event, not via a service provider's `$this->commands()`.
