# AUR

Publish to the Arch User Repository for Arch Linux users.

## Configuration

```yaml
publishers:
  - type: aur
    package: myapp-bin
```

## Options

| Option | Description | Default |
|--------|-------------|---------|
| `package` | AUR package name | `{project}-bin` |
| `maintainer` | Maintainer name | From git config |
| `description` | Package description | From project |
| `license` | License identifier | Auto-detected |
| `depends` | Runtime dependencies | `[]` |
| `optdepends` | Optional dependencies | `[]` |

## Examples

### Basic Package

```yaml
publishers:
  - type: aur
    package: core-bin
```

### With Dependencies

```yaml
publishers:
  - type: aur
    package: core-bin
    depends:
      - git
      - docker
    optdepends:
      - "podman: alternative container runtime"
```

## Environment Variables

| Variable | Description |
|----------|-------------|
| `AUR_SSH_KEY` | SSH private key for AUR push (required) |

## Setup

1. Create an AUR account at https://aur.archlinux.org

2. Add your SSH public key to your AUR account

3. Create the initial package:
   ```bash
   git clone ssh://aur@aur.archlinux.org/myapp-bin.git
   ```

4. After publishing, users install with:
   ```bash
   yay -S myapp-bin
   # or
   paru -S myapp-bin
   ```

## Generated PKGBUILD

```bash
# Maintainer: Your Name <email@example.com>
pkgname=myapp-bin
pkgver=1.2.3
pkgrel=1
pkgdesc="CLI for building and deploying applications"
arch=('x86_64' 'aarch64')
url="https://github.com/org/myapp"
license=('MIT')
depends=('glibc')
source_x86_64=("${url}/releases/download/v${pkgver}/myapp_linux_amd64.tar.gz")
source_aarch64=("${url}/releases/download/v${pkgver}/myapp_linux_arm64.tar.gz")
sha256sums_x86_64=('abc123...')
sha256sums_aarch64=('def456...')

package() {
  install -Dm755 myapp "${pkgdir}/usr/bin/myapp"
}
```