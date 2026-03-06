# npm

Publish JavaScript/TypeScript packages to the npm registry.

## Configuration

```yaml
publishers:
  - type: npm
    package: "@org/myapp"
    access: public
```

## Options

| Option | Description | Default |
|--------|-------------|---------|
| `package` | Package name | From `package.json` |
| `access` | Access level (`public`, `restricted`) | `restricted` |
| `tag` | Distribution tag | `latest` |
| `directory` | Package directory | `.` |

## Examples

### Public Package

```yaml
publishers:
  - type: npm
    package: "@host-uk/cli"
    access: public
```

### Scoped Private Package

```yaml
publishers:
  - type: npm
    package: "@myorg/internal-tool"
    access: restricted
```

### Beta Release

```yaml
publishers:
  - type: npm
    tag: beta
```

### Monorepo Package

```yaml
publishers:
  - type: npm
    directory: packages/sdk
```

## Environment Variables

| Variable | Description |
|----------|-------------|
| `NPM_TOKEN` | npm access token (required) |

## Setup

1. Create an npm access token:
   ```bash
   npm token create --read-only=false
   ```

2. Add to your CI environment as `NPM_TOKEN`

3. For scoped packages, ensure the scope is linked to your org:
   ```bash
   npm login --scope=@myorg
   ```
