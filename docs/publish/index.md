# Publish

Release your applications to package managers, container registries, and distribution platforms.

## Publishers

| Provider | Description |
|----------|-------------|
| [GitHub](./github) | GitHub Releases with assets |
| [Docker](./docker) | Container registries (Docker Hub, GHCR, ECR) |
| [npm](./npm) | npm registry for JavaScript packages |
| [Homebrew](./homebrew) | macOS/Linux package manager |
| [Scoop](./scoop) | Windows package manager |
| [AUR](./aur) | Arch User Repository |
| [Chocolatey](./chocolatey) | Windows package manager |
| [LinuxKit](./linuxkit) | Bootable Linux images |

## Quick Start

```bash
# 1. Build your artifacts
core build

# 2. Preview release (dry-run)
core ci

# 3. Publish (requires explicit flag)
core ci --we-are-go-for-launch
```

## Configuration

Publishers are configured in `.core/release.yaml`:

```yaml
version: 1

project:
  name: myapp
  repository: org/myapp

publishers:
  - type: github

  - type: docker
    registry: ghcr.io
    image: org/myapp
```

## Safety

All publish commands are **dry-run by default**. Use `--we-are-go-for-launch` to actually publish.

```bash
# Safe preview
core ci

# Actually publish
core ci --we-are-go-for-launch
```