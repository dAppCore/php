# Chocolatey

Publish to Chocolatey for Windows package management.

## Configuration

```yaml
publishers:
  - type: chocolatey
    package: myapp
```

## Options

| Option | Description | Default |
|--------|-------------|---------|
| `package` | Package ID | Project name |
| `title` | Display title | Package ID |
| `description` | Package description | From project |
| `authors` | Package authors | From git config |
| `license` | License URL | Auto-detected |
| `projectUrl` | Project homepage | Repository URL |
| `iconUrl` | Package icon URL | None |
| `tags` | Package tags | `[]` |

## Examples

### Basic Package

```yaml
publishers:
  - type: chocolatey
    package: core
```

### With Metadata

```yaml
publishers:
  - type: chocolatey
    package: core
    title: "Core CLI"
    description: "CLI for building and deploying applications"
    tags:
      - cli
      - devops
      - build
    iconUrl: https://example.com/icon.png
```

## Environment Variables

| Variable | Description |
|----------|-------------|
| `CHOCOLATEY_API_KEY` | Chocolatey API key (required) |

## Setup

1. Create a Chocolatey account at https://community.chocolatey.org

2. Get your API key from your account settings

3. After publishing, users install with:
   ```powershell
   choco install myapp
   ```

## Generated nuspec

```xml
<?xml version="1.0" encoding="utf-8"?>
<package xmlns="http://schemas.microsoft.com/packaging/2015/06/nuspec.xsd">
  <metadata>
    <id>myapp</id>
    <version>1.2.3</version>
    <title>Core CLI</title>
    <authors>Host UK</authors>
    <description>CLI for building and deploying applications</description>
    <projectUrl>https://github.com/org/myapp</projectUrl>
    <licenseUrl>https://github.com/org/myapp/blob/main/LICENSE</licenseUrl>
    <tags>cli devops build</tags>
  </metadata>
  <files>
    <file src="tools\**" target="tools" />
  </files>
</package>
```