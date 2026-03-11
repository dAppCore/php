---
title: core/php
description: Go-powered PHP/Laravel development toolkit with FrankenPHP embedding, service orchestration, CI pipelines, and Coolify deployment.
---

# core/php

`forge.lthn.ai/core/php` is a Go module that provides a comprehensive CLI toolkit
for PHP and Laravel development. It covers the full lifecycle: local development
with service orchestration, code quality assurance, Docker/LinuxKit image building,
and production deployment via the Coolify API.

The module also embeds FrankenPHP, allowing Laravel applications to be served
from a single Go binary with Octane worker mode for sub-millisecond response
times.


## Quick Start

### As a standalone binary

```bash
# Build the core-php binary
core build
# -- or --
go build -o bin/core-php ./cmd/core-php

# Start the Laravel development environment
core-php dev

# Run the CI pipeline
core-php ci
```

### As a library in a Go application

```go
import php "forge.lthn.ai/core/php"

// Register commands under a "php" parent command
cli.Main(
    cli.WithCommands("php", php.AddPHPRootCommands),
)
```


## Package Layout

| File / Directory | Purpose |
|---|---|
| `cmd/core-php/main.go` | Binary entry point -- registers all commands and calls `cli.Main()` |
| `cmd.go` | Top-level command registration (`AddPHPCommands`, `AddPHPRootCommands`) |
| `cmd_dev.go` | `dev`, `logs`, `stop`, `status`, `ssl` commands |
| `cmd_build.go` | `build` (Docker/LinuxKit) and `serve` (production container) commands |
| `cmd_ci.go` | `ci` command -- full QA pipeline with JSON/Markdown/SARIF output |
| `cmd_deploy.go` | `deploy`, `deploy:status`, `deploy:rollback`, `deploy:list` commands |
| `cmd_packages.go` | `packages link/unlink/update/list` commands |
| `cmd_serve_frankenphp.go` | `serve:embedded` and `exec` commands (CGO only) |
| `cmd_commands.go` | `AddCommands()` convenience wrapper |
| `handler.go` | FrankenPHP HTTP handler (`Handler`) -- CGO build tag |
| `bridge.go` | Native bridge -- localhost HTTP API for PHP-to-Go calls |
| `php.go` | `DevServer` -- multi-service orchestration (start, stop, logs, status) |
| `services.go` | `Service` interface and concrete implementations (FrankenPHP, Vite, Horizon, Reverb, Redis) |
| `detect.go` | Project detection: Laravel, FrankenPHP, Vite, Horizon, Reverb, Redis, package managers |
| `dockerfile.go` | Auto-generated Dockerfiles from `composer.json` analysis |
| `container.go` | `DockerBuildOptions`, `LinuxKitBuildOptions`, `ServeOptions`, and build/serve functions |
| `deploy.go` | Deployment orchestration -- `Deploy()`, `Rollback()`, `DeployStatus()` |
| `coolify.go` | Coolify API client (`CoolifyClient`) with deploy, rollback, status, and list operations |
| `quality.go` | QA tools: Pint, PHPStan/Larastan, Psalm, Rector, Infection, security checks, audit |
| `testing.go` | Test runner detection (Pest/PHPUnit) and execution |
| `ssl.go` | SSL certificate management via mkcert |
| `packages.go` | Composer path repository management (link/unlink local packages) |
| `env.go` | Runtime environment setup for embedded apps (CGO only) |
| `extract.go` | `Extract()` -- copies an `embed.FS` Laravel app to a temporary directory |
| `workspace.go` | Workspace configuration (`.core/workspace.yaml`) for multi-package repos |
| `i18n.go` | Locale registration for internationalised CLI strings |
| `services_unix.go` | Unix process group management (SIGTERM/SIGKILL) |
| `services_windows.go` | Windows process termination |
| `.core/build.yaml` | Build configuration for `core build` |


## Dependencies

| Module | Role |
|---|---|
| `forge.lthn.ai/core/cli` | CLI framework (Cobra wrapper, TUI styles, output helpers) |
| `forge.lthn.ai/core/go-i18n` | Internationalisation for command descriptions and messages |
| `forge.lthn.ai/core/go-io` | Filesystem abstraction (`Medium` interface) for testability |
| `forge.lthn.ai/core/go-process` | Process management utilities |
| `github.com/dunglas/frankenphp` | FrankenPHP embedding (CGO, optional) |
| `gopkg.in/yaml.v3` | YAML parsing for workspace configuration |


## Licence

EUPL-1.2
