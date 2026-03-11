---
title: Architecture
description: Internal design of core/php -- FrankenPHP handler, service orchestration, project detection, CI pipeline, deployment, and native bridge.
---

# Architecture

This document explains how the Go code in `forge.lthn.ai/core/php` is structured
and how the major subsystems interact.


## Command Registration

The module exposes two entry points for command registration:

- **`AddPHPCommands(root)`** -- adds commands under a `php` parent (for the
  multi-purpose `core` binary where PHP is one of many command groups).
- **`AddPHPRootCommands(root)`** -- adds commands directly to the root (for the
  standalone `core-php` binary where `dev`, `build`, etc. are top-level).

Both paths register the same set of commands and share workspace-aware
`PersistentPreRunE` logic that detects `.core/workspace.yaml` and `cd`s into the
active package directory before execution.

The standalone binary is minimal:

```go
// cmd/core-php/main.go
func main() {
    cli.Main(
        cli.WithCommands("php", php.AddPHPRootCommands),
    )
}
```

### Command Tree

```
core-php (or core php)
  dev               Start all detected services (FrankenPHP, Vite, Horizon, Reverb, Redis)
  logs              Stream unified or per-service logs
  stop              Stop all running services
  status            Show project info and service detection
  ssl               Setup mkcert SSL certificates
  build             Build Docker or LinuxKit image
  serve             Run a production Docker container
  shell <container> Open a shell in a running container
  ci                Run full QA pipeline (test, stan, psalm, fmt, audit, security)
  packages
    link <paths>    Add Composer path repositories for local development
    unlink <names>  Remove path repositories
    update [pkgs]   Run composer update
    list            List linked packages
  deploy            Trigger Coolify deployment
  deploy:status     Check deployment status
  deploy:rollback   Rollback to a previous deployment
  deploy:list       List recent deployments
  serve:embedded    (CGO only) Serve via embedded FrankenPHP runtime
  exec <cmd>        (CGO only) Execute artisan via FrankenPHP
```


## FrankenPHP Handler (CGO)

Files: `handler.go`, `cmd_serve_frankenphp.go`, `env.go`, `extract.go`

Build tag: `//go:build cgo`

The `Handler` struct implements `http.Handler` and delegates all PHP processing
to the FrankenPHP C library. It supports two modes:

1. **Octane worker mode** -- Laravel stays booted in memory across requests.
   Workers are persistent PHP processes that handle requests without
   re-bootstrapping. This yields sub-millisecond response times.
2. **Standard mode** -- each request boots the PHP application from scratch.
   Used as a fallback when Octane is not installed.

### Request Routing

`Handler.ServeHTTP` implements a try-files pattern similar to Caddy/Nginx:

1. If the URL maps to a directory, rewrite to `{dir}/index.php`.
2. If the file does not exist and the URL does not end in `.php`, rewrite to
   `/index.php` (front controller).
3. Non-PHP files that exist on disc are served directly via `http.ServeFile`.
4. Everything else is passed to `frankenphp.ServeHTTP`.

### Initialisation

```go
handler, cleanup, err := php.NewHandler(laravelRoot, php.HandlerConfig{
    NumThreads: 4,
    NumWorkers: 2,
    PHPIni: map[string]string{
        "display_errors": "Off",
        "opcache.enable": "1",
    },
})
defer cleanup()
```

`NewHandler` tries to initialise FrankenPHP with workers first. If
`vendor/laravel/octane/bin/frankenphp-worker.php` exists, it passes the worker
script to `frankenphp.Init`. If that fails, it falls back to standard mode.

### Embedded Applications

`Extract()` copies an `embed.FS`-packaged Laravel application to a temporary
directory so that FrankenPHP can access real filesystem paths.
`PrepareRuntimeEnvironment()` then creates persistent data directories
(`~/Library/Application Support/{app}` on macOS, `~/.local/share/{app}` on
Linux), generates a `.env` file with an auto-generated `APP_KEY`, symlinks
`storage/` to the persistent location, and creates an empty SQLite database.


## Native Bridge

File: `bridge.go`

The bridge is a localhost-only HTTP server that allows PHP code to call back into
Go. This is needed because Livewire renders server-side in PHP and cannot call
Wails bindings (`window.go.*`) directly.

```go
bridge, err := php.NewBridge(myHandler)
// PHP can now POST to http://127.0.0.1:{bridge.Port()}/bridge/call
```

The bridge exposes two endpoints:

| Method | Path | Purpose |
|---|---|---|
| GET | `/bridge/health` | Health check (returns `{"status":"ok"}`) |
| POST | `/bridge/call` | Invoke a named method with JSON arguments |

The host application implements `BridgeHandler`:

```go
type BridgeHandler interface {
    HandleBridgeCall(method string, args json.RawMessage) (any, error)
}
```

The bridge port is injected into Laravel's `.env` as `NATIVE_BRIDGE_URL`.


## Service Orchestration

Files: `php.go`, `services.go`, `services_unix.go`, `services_windows.go`

### DevServer

`DevServer` manages the lifecycle of all development services. It:

1. Detects which services are needed (via `DetectServices`).
2. Filters out services disabled by flags (`--no-vite`, `--no-horizon`, etc.).
3. Creates concrete service instances.
4. Starts all services, rolling back if any fail.
5. Provides unified log streaming (round-robin multiplexing from all service log files).
6. Stops services in reverse order on shutdown.

### Service Interface

All managed services implement:

```go
type Service interface {
    Name() string
    Start(ctx context.Context) error
    Stop() error
    Logs(follow bool) (io.ReadCloser, error)
    Status() ServiceStatus
}
```

### Concrete Services

| Service | Binary | Default Port | Notes |
|---|---|---|---|
| `FrankenPHPService` | `php artisan octane:start --server=frankenphp` | 8000 | HTTPS via mkcert certificates |
| `ViteService` | `npm/pnpm/yarn/bun run dev` | 5173 | Auto-detects package manager |
| `HorizonService` | `php artisan horizon` | -- | Uses `horizon:terminate` for graceful stop |
| `ReverbService` | `php artisan reverb:start` | 8080 | WebSocket server |
| `RedisService` | `redis-server` | 6379 | Optional config file support |

All services inherit from `baseService`, which handles:

- Process creation with platform-specific `SysProcAttr` for clean shutdown.
- Log file creation under `.core/logs/`.
- Background process monitoring.
- Graceful stop with SIGTERM, then SIGKILL after 5 seconds.


## Project Detection

File: `detect.go`

The detection system inspects the filesystem to determine project capabilities:

| Function | Checks |
|---|---|
| `IsLaravelProject(dir)` | `artisan` exists and `composer.json` requires `laravel/framework` |
| `IsFrankenPHPProject(dir)` | `laravel/octane` in `composer.json`, `config/octane.php` mentions frankenphp |
| `IsPHPProject(dir)` | `composer.json` exists |
| `DetectServices(dir)` | Checks for Vite configs, Horizon config, Reverb config, Redis in `.env` |
| `DetectPackageManager(dir)` | Inspects lock files: `bun.lockb`, `pnpm-lock.yaml`, `yarn.lock`, `package-lock.json` |
| `GetLaravelAppName(dir)` | Reads `APP_NAME` from `.env` |
| `GetLaravelAppURL(dir)` | Reads `APP_URL` from `.env` |


## Dockerfile Generation

File: `dockerfile.go`

`GenerateDockerfile(dir)` produces a multi-stage Dockerfile by analysing
`composer.json`:

1. **PHP version** -- extracted from `composer.json`'s `require.php` constraint.
2. **Extensions** -- inferred from package dependencies (e.g., `laravel/horizon`
   implies `redis` and `pcntl`; `intervention/image` implies `gd`).
3. **Frontend assets** -- if `package.json` has a `build` script, a Node.js
   build stage is prepended.
4. **Base image** -- `dunglas/frankenphp` with Alpine variant by default.

The generated Dockerfile includes:

- Multi-stage build for frontend assets (Node 20 Alpine).
- Composer dependency installation with layer caching.
- Laravel config/route/view caching.
- Correct permissions for `storage/` and `bootstrap/cache/`.
- Health check via `curl -f http://localhost/up`.
- Octane start command if `laravel/octane` is detected.


## CI Pipeline

File: `cmd_ci.go`, `quality.go`, `testing.go`

The `ci` command runs six checks in sequence:

| Check | Tool | SARIF Support |
|---|---|---|
| `test` | Pest or PHPUnit (auto-detected) | No |
| `stan` | PHPStan or Larastan (auto-detected) | Yes |
| `psalm` | Psalm (skipped if not configured) | Yes |
| `fmt` | Laravel Pint (check-only mode) | No |
| `audit` | `composer audit` + `npm audit` | No |
| `security` | `.env` and filesystem security checks | No |

### Output Formats

- **Default** -- coloured terminal table with per-check status icons.
- **`--json`** -- structured `CIResult` JSON with per-check details.
- **`--summary`** -- Markdown table suitable for PR comments.
- **`--sarif`** -- SARIF files for stan/psalm, uploadable to GitHub Security.
- **`--upload-sarif`** -- uploads SARIF files via `gh api`.

### Failure Threshold

The `--fail-on` flag controls when the pipeline returns a non-zero exit code:

| Value | Fails On |
|---|---|
| `critical` | Only if issues with `Issues > 0` |
| `high` / `error` (default) | Any check with status `failed` |
| `warning` | Any check with status `failed` or `warning` |

### QA Pipeline Stages

The `quality.go` file also defines a broader QA pipeline (`QAOptions`) with
three stages:

- **Quick** -- `audit`, `fmt`, `stan`
- **Standard** -- `psalm` (if configured), `test`
- **Full** -- `rector` (if configured), `infection` (if configured)


## Deployment (Coolify)

Files: `deploy.go`, `coolify.go`

### Configuration

Coolify credentials are loaded from environment variables or `.env`:

```
COOLIFY_URL=https://coolify.example.com
COOLIFY_TOKEN=your-api-token
COOLIFY_APP_ID=app-uuid
COOLIFY_STAGING_APP_ID=staging-app-uuid  (optional)
```

Environment variables take precedence over `.env` values.

### CoolifyClient

The `CoolifyClient` wraps the Coolify REST API:

```go
client := php.NewCoolifyClient(baseURL, token)
deployment, err := client.TriggerDeploy(ctx, appID, force)
deployment, err := client.GetDeployment(ctx, appID, deploymentID)
deployments, err := client.ListDeployments(ctx, appID, limit)
deployment, err := client.Rollback(ctx, appID, deploymentID)
app, err := client.GetApp(ctx, appID)
```

### Deployment Flow

1. Load config from `.env` or environment.
2. Resolve the app ID for the target environment (production or staging).
3. Trigger deployment via the Coolify API.
4. If `--wait` is set, poll every 5 seconds (up to 10 minutes) until the
   deployment reaches a terminal state.
5. Print deployment status with coloured output.

### Rollback

If no specific deployment ID is provided, `Rollback()` fetches the 10 most
recent deployments, skips the current one, and rolls back to the last
successful deployment.


## Workspace Support

File: `workspace.go`

For multi-package repositories, a `.core/workspace.yaml` file at the workspace
root can set an active package:

```yaml
version: 1
active: core-tenant
packages_dir: ./packages
```

When present, the `PersistentPreRunE` hook automatically changes the working
directory to the active package before command execution. The workspace root is
found by walking up the directory tree.


## SSL Certificates

File: `ssl.go`

The `ssl` command and `--https` flag use [mkcert](https://github.com/FiloSottile/mkcert)
to generate locally-trusted SSL certificates. Certificates are stored in
`~/.core/ssl/` by default.

The `SetupSSLIfNeeded()` function is idempotent: it checks for existing
certificates before generating new ones. Generated certificates cover the
domain, `localhost`, `127.0.0.1`, and `::1`.


## Filesystem Abstraction

The module uses `io.Medium` from `forge.lthn.ai/core/go-io` for all filesystem
operations. The default medium is `io.Local` (real filesystem), but tests can
inject a mock medium via `SetMedium()`:

```go
php.SetMedium(myMockMedium)
defer php.SetMedium(io.Local)
```

This allows the detection, Dockerfile generation, package management, and
security check code to be tested without touching the real filesystem.
