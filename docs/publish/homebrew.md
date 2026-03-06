# Homebrew

Publish to Homebrew for macOS and Linux package management.

## Configuration

```yaml
publishers:
  - type: homebrew
    tap: org/homebrew-tap
```

## Options

| Option | Description | Default |
|--------|-------------|---------|
| `tap` | Tap repository (`org/homebrew-tap`) | Required |
| `formula` | Formula name | Project name |
| `homepage` | Project homepage | Repository URL |
| `description` | Package description | From project |
| `license` | License identifier | Auto-detected |
| `dependencies` | Homebrew dependencies | `[]` |

## Examples

### Basic Formula

```yaml
publishers:
  - type: homebrew
    tap: host-uk/homebrew-tap
```

### With Dependencies

```yaml
publishers:
  - type: homebrew
    tap: host-uk/homebrew-tap
    dependencies:
      - git
      - go
```

### Custom Description

```yaml
publishers:
  - type: homebrew
    tap: host-uk/homebrew-tap
    description: "CLI for building and deploying applications"
    homepage: https://core.host.uk.com
```

## Environment Variables

| Variable | Description |
|----------|-------------|
| `GITHUB_TOKEN` | Token with repo access to tap (required) |

## Setup

1. Create a tap repository: `org/homebrew-tap`

2. Ensure your `GITHUB_TOKEN` has push access to the tap

3. After publishing, users install with:
   ```bash
   brew tap org/tap
   brew install myapp
   ```

## Generated Formula

```ruby
class Myapp < Formula
  desc "CLI for building and deploying applications"
  homepage "https://github.com/org/myapp"
  version "1.2.3"
  license "MIT"

  on_macos do
    if Hardware::CPU.arm?
      url "https://github.com/org/myapp/releases/download/v1.2.3/myapp_darwin_arm64.tar.gz"
      sha256 "abc123..."
    else
      url "https://github.com/org/myapp/releases/download/v1.2.3/myapp_darwin_amd64.tar.gz"
      sha256 "def456..."
    end
  end

  def install
    bin.install "myapp"
  end
end
```
