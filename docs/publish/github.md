# GitHub Releases

Publish releases to GitHub with binary assets, checksums, and changelog.

## Configuration

```yaml
publishers:
  - type: github
```

## Options

| Option | Description | Default |
|--------|-------------|---------|
| `draft` | Create as draft release | `false` |
| `prerelease` | Mark as prerelease | `false` |
| `assets` | Additional asset patterns | Auto-detected |

## Examples

### Basic Release

```yaml
publishers:
  - type: github
```

Automatically uploads:
- Built binaries from `dist/`
- SHA256 checksums
- Generated changelog

### Draft Release

```yaml
publishers:
  - type: github
    draft: true
```

### Prerelease

```yaml
publishers:
  - type: github
    prerelease: true
```

### Custom Assets

```yaml
publishers:
  - type: github
    assets:
      - dist/*.tar.gz
      - dist/*.zip
      - docs/manual.pdf
```

## Environment Variables

| Variable | Description |
|----------|-------------|
| `GITHUB_TOKEN` | GitHub personal access token (required) |

## Generated Assets

For a cross-platform Go build, GitHub releases include:

```
myapp_1.0.0_linux_amd64.tar.gz
myapp_1.0.0_linux_arm64.tar.gz
myapp_1.0.0_darwin_amd64.tar.gz
myapp_1.0.0_darwin_arm64.tar.gz
myapp_1.0.0_windows_amd64.zip
checksums.txt
```
