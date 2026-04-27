# Front/Cli

CLI/Artisan frontage. Fires `ConsoleBooting` lifecycle event and processes module registrations.

## Files

- **Boot.php** -- ServiceProvider that only runs in console context. Registers the `ScheduleServiceProvider`, fires `ConsoleBooting` event, then processes module requests collected by the event: Artisan commands, translations, middleware aliases, Gate policies, and Blade component paths. This is how modules register CLI-specific resources without coupling to the console context directly.

## Event-Driven Registration

Modules listen for `ConsoleBooting` and call methods on the event to register:
- `commandRequests()` -- Artisan command classes
- `translationRequests()` -- `[namespace, path]` pairs
- `middlewareRequests()` -- `[alias, class]` pairs
- `policyRequests()` -- `[model, policy]` pairs
- `bladeComponentRequests()` -- `[path, namespace]` pairs
