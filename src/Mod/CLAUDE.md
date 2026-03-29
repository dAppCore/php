# Mod -- Module Aggregator

`Core\Mod\Boot` is a minimal ServiceProvider that acts as the namespace root for feature modules bundled with the core-php package.

## How It Works

Each module lives in a subdirectory (e.g., `Mod/Trees/`) with its own `Boot.php`. Modules self-register through the lifecycle event system -- they declare a static `$listens` array and are discovered by `ModuleScanner` during `LifecycleEventProvider::register()`.

`Mod\Boot` itself does nothing in `register()` or `boot()`. It exists purely as the ServiceProvider entry point listed in `Core\Boot::$providers`.

## Module Structure Convention

```
Mod/
  {ModuleName}/
    Boot.php              # $listens declaration + event handlers
    Console/              # Artisan commands
    Controllers/          # HTTP controllers (Api/, Web/)
    Database/Seeders/     # Database seeders
    Jobs/                 # Queue jobs
    Lang/                 # Translation files
    Listeners/            # Event listeners
    Middleware/            # HTTP middleware
    Models/               # Eloquent models
    Notifications/        # Notification classes
    Routes/               # Route files (api.php, web.php)
    Tests/                # Feature and Unit tests
    View/                 # Blade templates and Livewire components
      Blade/              # Blade view files
      Modal/              # Livewire components (organized by frontage)
```

## Namespace

`Core\Mod\{ModuleName}` -- autoloaded via PSR-4 from `src/Mod/`.

## Adding a Module

1. Create `Mod/{Name}/Boot.php` with `public static array $listens`
2. Implement event handler methods referenced in `$listens`
3. The scanner discovers it automatically -- no registration needed

## Current Modules

- `Trees/` -- Trees for Agents initiative (see `Trees/CLAUDE.md`)
