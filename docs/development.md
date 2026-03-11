---
title: Development
description: How to build, test, and contribute to core/php.
---

# Development

This guide covers building the `core-php` binary, running the test suite, and
contributing to the project.


## Prerequisites

- **Go 1.26+** (the module uses Go 1.26 features)
- **CGO toolchain** (optional, required only for FrankenPHP embedding)
- **Docker** (for container build/serve commands)
- **mkcert** (optional, for local SSL certificates)
- **PHP 8.3+** with Composer (for the PHP side of the project)
- **Node.js 20+** (optional, for frontend asset building)


## Building

### Standard build (no CGO)

The default build produces a binary without FrankenPHP embedding. The embedded
FrankenPHP commands (`serve:embedded`, `exec`) are excluded.

```bash
# Using the core CLI
core build

# Using go directly
go build -trimpath -ldflags="-s -w" -o bin/core-php ./cmd/core-php
```

Build configuration lives in `.core/build.yaml`:

```yaml
project:
  name: core-php
  main: ./cmd/core-php
  binary: core-php

build:
  cgo: false
  flags:
    - -trimpath
  ldflags:
    - -s
    - -w

targets:
  - os: linux
    arch: amd64
  - os: linux
    arch: arm64
  - os: darwin
    arch: arm64
  - os: windows
    arch: amd64
```

### CGO build (with FrankenPHP)

To include the embedded FrankenPHP handler, enable CGO:

```bash
CGO_ENABLED=1 go build -trimpath -o bin/core-php ./cmd/core-php
```

This pulls in `github.com/dunglas/frankenphp` and links against the PHP C
library. The resulting binary can serve Laravel applications without a separate
PHP installation.


## Running Tests

```bash
# All Go tests
core go test
# -- or --
go test ./...

# Single test
core go test --run TestDetectServices
# -- or --
go test -run TestDetectServices ./...

# With race detector
go test -race ./...

# Coverage
core go cov
core go cov --open   # Opens HTML report
```

### Test Conventions

Tests follow the `_Good`, `_Bad`, `_Ugly` suffix pattern from the Core
framework:

- **`_Good`** -- happy path, expected to succeed.
- **`_Bad`** -- expected error conditions, verifying error handling.
- **`_Ugly`** -- edge cases, panics, unusual inputs.

### Mock Filesystem

Tests that exercise detection, Dockerfile generation, or package management use
a mock `io.Medium` to avoid filesystem side effects:

```go
func TestDetectServices_Good(t *testing.T) {
    mock := io.NewMockMedium()
    mock.WriteFile("artisan", "")
    mock.WriteFile("composer.json", `{"require":{"laravel/framework":"^11.0"}}`)
    mock.WriteFile("vite.config.js", "")

    php.SetMedium(mock)
    defer php.SetMedium(io.Local)

    services := php.DetectServices(".")
    assert.Contains(t, services, php.ServiceFrankenPHP)
    assert.Contains(t, services, php.ServiceVite)
}
```

### Test Files

| File | Covers |
|---|---|
| `php_test.go` | DevServer lifecycle, service filtering, options |
| `container_test.go` | Docker build, LinuxKit build, serve options |
| `detect_test.go` | Project detection, service detection, package manager detection |
| `dockerfile_test.go` | Dockerfile generation, PHP extension detection, version extraction |
| `deploy_test.go` | Deployment flow, rollback, status checking |
| `deploy_internal_test.go` | Internal deployment helpers |
| `coolify_test.go` | Coolify API client (HTTP mocking) |
| `packages_test.go` | Package linking, unlinking, listing |
| `services_test.go` | Service interface, base service, start/stop |
| `services_extended_test.go` | Extended service scenarios |
| `ssl_test.go` | SSL certificate paths, existence checking |
| `ssl_extended_test.go` | Extended SSL scenarios |


## Code Quality

```bash
# Format Go code
core go fmt

# Vet
core go vet

# Lint
core go lint

# Full QA (fmt + vet + lint + test)
core go qa

# Full QA with race detection, vulnerability scan, security checks
core go qa full
```


## Project Structure

```
forge.lthn.ai/core/php/
  cmd/
    core-php/
      main.go             # Binary entry point
  locales/
    *.json                # Internationalised CLI strings
  docker/
    docker-compose.prod.yml
  stubs/                  # Template stubs
  config/                 # PHP configuration templates
  src/                    # PHP framework source (separate from Go code)
  tests/                  # PHP tests
  docs/                   # Documentation (this directory)
  .core/
    build.yaml            # Build configuration
  *.go                    # Go source (flat layout, single package)
```

The Go code uses a flat package layout -- all `.go` files are in the root
`php` package. This keeps imports simple: `import php "forge.lthn.ai/core/php"`.


## Adding a New Command

1. Create a new file `cmd_mycommand.go`.
2. Define the registration function:

```go
func addPHPMyCommand(parent *cli.Command) {
    cmd := &cli.Command{
        Use:   "mycommand",
        Short: i18n.T("cmd.php.mycommand.short"),
        RunE: func(cmd *cli.Command, args []string) error {
            // Implementation
            return nil
        },
    }
    parent.AddCommand(cmd)
}
```

3. Register it in `cmd.go` inside both `AddPHPCommands` and
   `AddPHPRootCommands`:

```go
addPHPMyCommand(phpCmd)  // or root, for standalone binary
```

4. Add the i18n key to `locales/en.json`.


## Adding a New Service

1. Define the service struct in `services.go`, embedding `baseService`:

```go
type MyService struct {
    baseService
}

func NewMyService(dir string) *MyService {
    return &MyService{
        baseService: baseService{
            name: "MyService",
            port: 9999,
            dir:  dir,
        },
    }
}

func (s *MyService) Start(ctx context.Context) error {
    return s.startProcess(ctx, "my-binary", []string{"--flag"}, nil)
}

func (s *MyService) Stop() error {
    return s.stopProcess()
}
```

2. Add a `DetectedService` constant in `detect.go`:

```go
const ServiceMyService DetectedService = "myservice"
```

3. Add detection logic in `DetectServices()`.

4. Add a case in `DevServer.Start()` in `php.go`.


## Internationalisation

All user-facing strings use `i18n.T()` keys rather than hardcoded English.
Locale files live in `locales/` and are embedded via `//go:embed`:

```go
//go:embed locales/*.json
var localeFS embed.FS

func init() {
    i18n.RegisterLocales(localeFS, "locales")
}
```

When adding new commands or messages, add the corresponding keys to the locale
files.


## Contributing

- Follow UK English conventions: colour, organisation, centre.
- All code is licenced under EUPL-1.2.
- Run `core go qa` before submitting changes.
- Use conventional commits: `type(scope): description`.
- Include `Co-Authored-By: Virgil <virgil@lethean.io>` if pair-programming with
  the AI agent.
