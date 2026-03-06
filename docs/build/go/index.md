# Core Go

Core is a Go framework for the host-uk ecosystem - build, release, and deploy Go, Wails, PHP, and container workloads.

## Installation

```bash
# Via Go (recommended)
go install github.com/host-uk/core/cmd/core@latest

# Or download binary from releases
curl -Lo core https://github.com/host-uk/core/releases/latest/download/core-$(go env GOOS)-$(go env GOARCH)
chmod +x core && sudo mv core /usr/local/bin/

# Verify
core doctor
```

See [Getting Started](getting-started.md) for all installation options including building from source.

## Command Reference

See [CLI](/build/cli/) for full command documentation.

| Command | Description |
|---------|-------------|
| [go](/build/cli/go/) | Go development (test, fmt, lint, cov) |
| [php](/build/cli/php/) | Laravel/PHP development |
| [build](/build/cli/build/) | Build Go, Wails, Docker, LinuxKit projects |
| [ci](/build/cli/ci/) | Publish releases (dry-run by default) |
| [sdk](/build/cli/sdk/) | SDK generation and validation |
| [dev](/build/cli/dev/) | Multi-repo workflow + dev environment |
| [pkg](/build/cli/pkg/) | Package search and install |
| [vm](/build/cli/vm/) | LinuxKit VM management |
| [docs](/build/cli/docs/) | Documentation management |
| [setup](/build/cli/setup/) | Clone repos from registry |
| [doctor](/build/cli/doctor/) | Check development environment |

## Quick Start

```bash
# Go development
core go test              # Run tests
core go test --coverage   # With coverage
core go fmt               # Format code
core go lint              # Lint code

# Build
core build                # Auto-detect and build
core build --targets linux/amd64,darwin/arm64

# Release (dry-run by default)
core ci                   # Preview release
core ci --we-are-go-for-launch  # Actually publish

# Multi-repo workflow
core dev work             # Status + commit + push
core dev work --status    # Just show status

# PHP development
core php dev              # Start dev environment
core php test             # Run tests
```

## Configuration

Core uses `.core/` directory for project configuration:

```
.core/
├── release.yaml    # Release targets and settings
├── build.yaml      # Build configuration (optional)
└── linuxkit/       # LinuxKit templates
```

And `repos.yaml` in workspace root for multi-repo management.

## Guides

- [Getting Started](getting-started.md) - Installation and first steps
- [Workflows](workflows.md) - Common task sequences
- [Troubleshooting](troubleshooting.md) - When things go wrong
- [Migration](migration.md) - Moving from legacy tools

## Reference

- [Configuration](configuration.md) - All config options
- [Glossary](glossary.md) - Term definitions

## Claude Code Skill

Install the skill to teach Claude Code how to use the Core CLI:

```bash
curl -fsSL https://raw.githubusercontent.com/host-uk/core/main/.claude/skills/core/install.sh | bash
```

See [skill/](skill/) for details.
