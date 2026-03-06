---
title: Architecture
description: Technical architecture of the core-developer package
updated: 2026-01-29
---

# Architecture

The `core-developer` package provides administrative developer tools for the Host UK platform. It is designed exclusively for "Hades" tier users (god-mode access) and includes debugging, monitoring, and server management capabilities.

## Package Overview

| Aspect | Detail |
|--------|--------|
| Namespace | `Core\Developer\` |
| Type | L1 Module (Laravel Package) |
| Dependencies | `host-uk/core`, `host-uk/core-admin` |
| PHP Version | 8.2+ |
| Laravel Version | 11.x / 12.x |
| Livewire Version | 3.x / 4.x |

## Directory Structure

```
src/
├── Boot.php                    # Service provider & event handlers
├── Controllers/
│   └── DevController.php       # REST API endpoints
├── Concerns/
│   └── RemoteServerManager.php # SSH connection trait
├── Console/Commands/
│   └── CopyDeviceFrames.php    # Asset management command
├── Data/
│   └── RouteTestResult.php     # DTO for route test results
├── Exceptions/
│   └── SshConnectionException.php
├── Lang/
│   └── en_GB/developer.php     # Translations
├── Listeners/
│   └── SetHadesCookie.php      # Login event listener
├── Middleware/
│   ├── ApplyIconSettings.php   # Icon preferences from cookies
│   └── RequireHades.php        # Authorization middleware
├── Migrations/
│   └── 0001_01_01_000001_create_developer_tables.php
├── Models/
│   └── Server.php              # SSH server model
├── Providers/
│   ├── HorizonServiceProvider.php
│   └── TelescopeServiceProvider.php
├── Routes/
│   └── admin.php               # Route definitions
├── Services/
│   ├── LogReaderService.php    # Log file parsing
│   └── RouteTestService.php    # Route testing logic
├── Tests/
│   └── UseCase/
│       └── DevToolsBasic.php   # Feature tests
└── View/
    ├── Blade/
    │   └── admin/              # Blade templates
    │       ├── activity-log.blade.php
    │       ├── cache.blade.php
    │       ├── database.blade.php
    │       ├── logs.blade.php
    │       ├── route-inspector.blade.php
    │       ├── routes.blade.php
    │       └── servers.blade.php
    └── Modal/
        └── Admin/              # Livewire components
            ├── ActivityLog.php
            ├── Cache.php
            ├── Database.php
            ├── Logs.php
            ├── RouteInspector.php
            ├── Routes.php
            └── Servers.php
```

## Event-Driven Module Loading

The module uses the Core Framework's event-driven lazy loading pattern. The `Boot` class declares which events it listens to:

```php
public static array $listens = [
    AdminPanelBooting::class => 'onAdminPanel',
    ConsoleBooting::class => 'onConsole',
];
```

This ensures routes, views, and commands are only registered when the admin panel or console is actually used.

### Lifecycle Events

| Event | Handler | What Happens |
|-------|---------|--------------|
| `AdminPanelBooting` | `onAdminPanel()` | Registers views, routes, Pulse override |
| `ConsoleBooting` | `onConsole()` | Registers Artisan commands |

## Core Components

### 1. Livewire Admin Pages

All admin pages are full-page Livewire components using attribute-based configuration:

```php
#[Title('Application Logs')]
#[Layout('hub::admin.layouts.app')]
class Logs extends Component
```

Each component:
- Checks Hades access in `mount()`
- Uses `developer::admin.{name}` view namespace
- Has corresponding Blade template in `View/Blade/admin/`

### 2. API Controller

`DevController` provides REST endpoints for:
- `/hub/api/dev/logs` - Recent log entries
- `/hub/api/dev/routes` - Route listing
- `/hub/api/dev/session` - Session/request info
- `/hub/api/dev/clear/{type}` - Cache clearing

All endpoints are protected by `RequireHades` middleware and rate limiting.

### 3. Services

**LogReaderService**
- Memory-efficient log reading (reads from end of file)
- Parses Laravel log format
- Automatic sensitive data redaction
- Multi-log file support (daily/single channels)

**RouteTestService**
- Route discovery and formatting
- Request building with parameters
- In-process request execution
- Response formatting and metrics

### 4. RemoteServerManager Trait

Provides SSH connection management for classes that need remote server access:

```php
class DeployApplication implements ShouldQueue
{
    use RemoteServerManager;

    public function handle(): void
    {
        $this->withConnection($this->server, function () {
            $this->run('cd /var/www && git pull');
        });
    }
}
```

Key methods:
- `connect()` / `disconnect()` - Connection lifecycle
- `withConnection()` - Guaranteed cleanup pattern
- `run()` / `runMany()` - Command execution
- `fileExists()` / `readFile()` / `writeFile()` - File operations
- `getDiskUsage()` / `getMemoryUsage()` - Server stats

## Data Flow

### Admin Page Request

```
Browser Request
    ↓
Laravel Router → /hub/dev/logs
    ↓
Livewire Component (Logs.php)
    ↓
mount() → checkHadesAccess()
    ↓
loadLogs() → LogReaderService
    ↓
render() → developer::admin.logs
    ↓
Response (HTML)
```

### API Request

```
Browser/JS Request
    ↓
Laravel Router → /hub/api/dev/logs
    ↓
RequireHades Middleware
    ↓
Rate Limiter (throttle:dev-logs)
    ↓
DevController::logs()
    ↓
LogReaderService
    ↓
Response (JSON)
```

### SSH Connection

```
Servers Component
    ↓
testConnection($serverId)
    ↓
Server::findOrFail()
    ↓
Write temp key file
    ↓
Process::run(['ssh', ...])
    ↓
Parse result
    ↓
Update server status
    ↓
Clean up temp file
```

## Database Schema

### servers table

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| workspace_id | bigint | FK to workspaces |
| name | varchar(128) | Display name |
| ip | varchar(45) | IPv4/IPv6 address |
| port | smallint | SSH port (default 22) |
| user | varchar(64) | SSH username |
| private_key | text | Encrypted SSH key |
| status | varchar(32) | pending/connected/failed |
| last_connected_at | timestamp | Last successful connection |
| timestamps | | created_at, updated_at |
| soft_deletes | | deleted_at |

Indexes:
- `workspace_id`
- `(workspace_id, status)` composite

## Admin Menu Structure

The module registers a "Dev Tools" menu group with these items:

```
Dev Tools (admin group, priority 80)
├── Logs        → /hub/dev/logs
├── Activity    → /hub/dev/activity
├── Servers     → /hub/dev/servers
├── Database    → /hub/dev/database
├── Routes      → /hub/dev/routes
├── Route Inspector → /hub/dev/route-inspector
└── Cache       → /hub/dev/cache
```

The menu is only visible to users with `admin` flag (Hades tier).

## Rate Limiting

API endpoints have rate limits configured in `Boot::configureRateLimiting()`:

| Limiter | Limit | Purpose |
|---------|-------|---------|
| `dev-cache-clear` | 10/min | Prevent rapid cache clears |
| `dev-logs` | 30/min | Log reading |
| `dev-routes` | 30/min | Route listing |
| `dev-session` | 60/min | Session info |

Rate limits are per-user (or per-IP for unauthenticated requests).

## Third-Party Integrations

### Laravel Telescope

Custom `TelescopeServiceProvider` configures:
- Gate for Hades-only access in production
- Entry filtering (errors, failed jobs in production)
- Sensitive header/parameter hiding

### Laravel Horizon

Custom `HorizonServiceProvider` configures:
- Gate for Hades-only access
- Notification routing from config (email, SMS, Slack)

### Laravel Pulse

Custom Pulse dashboard view override at `View/Blade/vendor/pulse/dashboard.blade.php`.

## Configuration

The module expects these config keys (should be in `config/developer.php`):

```php
return [
    // Hades cookie token
    'hades_token' => env('HADES_TOKEN'),

    // SSH settings
    'ssh' => [
        'connection_timeout' => 30,
        'command_timeout' => 60,
    ],

    // Horizon notifications
    'horizon' => [
        'mail_to' => env('HORIZON_MAIL_TO'),
        'sms_to' => env('HORIZON_SMS_TO'),
        'slack_webhook' => env('HORIZON_SLACK_WEBHOOK'),
        'slack_channel' => env('HORIZON_SLACK_CHANNEL', '#alerts'),
    ],
];
```

## Extension Points

### Adding New Admin Pages

1. Create Livewire component in `View/Modal/Admin/`
2. Create Blade view in `View/Blade/admin/`
3. Add route in `Routes/admin.php`
4. Add menu item in `Boot::adminMenuItems()`
5. Add translations in `Lang/en_GB/developer.php`

### Adding New API Endpoints

1. Add method to `DevController`
2. Add route in `Routes/admin.php` API group
3. Create rate limiter in `Boot::configureRateLimiting()`
4. Apply `throttle:limiter-name` middleware

### Using RemoteServerManager

```php
use Core\Developer\Concerns\RemoteServerManager;

class MyJob
{
    use RemoteServerManager;

    public function handle(Server $server): void
    {
        $this->withConnection($server, function () {
            // Commands executed on remote server
            $result = $this->run('whoami');
            // ...
        });
    }
}
```

## Performance Considerations

1. **Log Reading** - Uses backwards reading to avoid loading entire log into memory. Configurable `maxBytes` limit.

2. **Route Caching** - Routes are computed once per request. The `RouteInspector` uses `#[Computed(cache: true)]` for route list.

3. **Query Log** - Enabled only in local environment (`Boot::boot()`).

4. **SSH Connections** - Always disconnect via `withConnection()` pattern to prevent resource leaks.

## Dependencies

### Composer Requirements

- `host-uk/core` - Core framework
- `host-uk/core-admin` - Admin panel infrastructure
- `phpseclib3` - SSH connections (via RemoteServerManager)
- `spatie/laravel-activitylog` - Activity logging

### Frontend Dependencies

- Flux UI components
- Tailwind CSS
- Livewire 3.x

## Testing Strategy

Tests use Pest syntax and focus on:
- Page rendering and content
- Authorization enforcement
- API endpoint behaviour
- Service logic

Test database: SQLite in-memory with Telescope/Pulse disabled.
