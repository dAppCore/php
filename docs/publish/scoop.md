# Scoop

Publish to Scoop for Windows package management.

## Configuration

```yaml
publishers:
  - type: scoop
    bucket: org/scoop-bucket
```

## Options

| Option | Description | Default |
|--------|-------------|---------|
| `bucket` | Bucket repository (`org/scoop-bucket`) | Required |
| `name` | Manifest name | Project name |
| `homepage` | Project homepage | Repository URL |
| `description` | Package description | From project |
| `license` | License identifier | Auto-detected |

## Examples

### Basic Manifest

```yaml
publishers:
  - type: scoop
    bucket: host-uk/scoop-bucket
```

### With Description

```yaml
publishers:
  - type: scoop
    bucket: host-uk/scoop-bucket
    description: "CLI for building and deploying applications"
    homepage: https://core.host.uk.com
```

## Environment Variables

| Variable | Description |
|----------|-------------|
| `GITHUB_TOKEN` | Token with repo access to bucket (required) |

## Setup

1. Create a bucket repository: `org/scoop-bucket`

2. Ensure your `GITHUB_TOKEN` has push access to the bucket

3. After publishing, users install with:
   ```powershell
   scoop bucket add org https://github.com/org/scoop-bucket
   scoop install myapp
   ```

## Generated Manifest

```json
{
  "version": "1.2.3",
  "description": "CLI for building and deploying applications",
  "homepage": "https://github.com/org/myapp",
  "license": "MIT",
  "architecture": {
    "64bit": {
      "url": "https://github.com/org/myapp/releases/download/v1.2.3/myapp_windows_amd64.zip",
      "hash": "sha256:abc123..."
    }
  },
  "bin": "myapp.exe"
}
```