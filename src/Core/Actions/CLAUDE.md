# Actions

Single-purpose business logic pattern with scheduling support.

## What It Does

Provides the `Action` trait for extracting business logic from controllers/components into focused, testable classes. Each action does one thing via a `handle()` method and gets a static `run()` shortcut that resolves dependencies from the container.

Also provides attribute-driven scheduling: annotate an Action with `#[Scheduled]` and it gets wired into Laravel's scheduler automatically.

## Key Classes

| Class | Purpose |
|-------|---------|
| `Action` (trait) | Adds `static run(...$args)` that resolves via container and calls `handle()` |
| `Actionable` (interface) | Optional contract for type-hinting actions |
| `Scheduled` (attribute) | Marks an Action for scheduled execution with frequency string |
| `ScheduledAction` (model) | Eloquent model persisted to `scheduled_actions` table |
| `ScheduledActionScanner` | Discovers `#[Scheduled]` attributes by scanning directories |
| `ScheduleServiceProvider` | Reads enabled scheduled actions from DB and registers with Laravel scheduler |

## Public API

```php
// Use the Action pattern
class CreateOrder {
    use Action;
    public function handle(User $user, array $data): Order { ... }
}
CreateOrder::run($user, $data); // resolves from container

// Schedule an action
#[Scheduled(frequency: 'dailyAt:09:00', timezone: 'Europe/London')]
class PublishDigest {
    use Action;
    public function handle(): void { ... }
}
```

## Frequency String Format

`method:arg1,arg2` maps directly to Laravel Schedule methods:
- `everyMinute` / `hourly` / `daily` / `weekly` / `monthly`
- `dailyAt:09:00` / `weeklyOn:1,09:00` / `cron:* * * * *`

## Integration

- Scanner skips `Tests/` directories and `*Test.php` files
- ScheduleServiceProvider validates namespace (`App\`, `Core\`, `Mod\`) and frequency method against allowlists before executing
- Actions are placed in `app/Mod/{Module}/Actions/`

## Conventions

- One action per file, named after what it does: `CreatePage`, `SendInvoice`
- Dependencies injected via constructor
- `handle()` is the single public method
- Scheduling state is DB-driven (enable/disable without code changes)
