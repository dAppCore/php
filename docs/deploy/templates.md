# Templates

Pre-configured LinuxKit templates for common deployment scenarios.

## Available Templates

| Template | Description | Platforms |
|----------|-------------|-----------|
| `core-dev` | Full development environment with 100+ tools | linux/amd64, linux/arm64 |
| `server-php` | FrankenPHP production server | linux/amd64, linux/arm64 |
| `edge-node` | Minimal edge deployment | linux/amd64, linux/arm64 |

## Using Templates

### List Templates

```bash
core vm templates list
```

Output:
```
Available Templates:

  core-dev
    Full development environment with 100+ tools
    Platforms: linux/amd64, linux/arm64

  server-php
    FrankenPHP production server
    Platforms: linux/amd64, linux/arm64

  edge-node
    Minimal edge deployment
    Platforms: linux/amd64, linux/arm64
```

### Show Template Details

```bash
core vm templates show server-php
```

Output:
```
Template: server-php

Description: FrankenPHP production server

Platforms:
  - linux/amd64
  - linux/arm64

Formats:
  - iso
  - qcow2

Services:
  - sshd
  - frankenphp
  - php-fpm

Size: ~800MB
```

### Show Template Variables

```bash
core vm templates vars server-php
```

Output:
```
Variables for server-php:
  SSH_KEY      (required)  SSH public key
  DOMAIN       (optional)  Server domain name
  MEMORY       (optional)  Memory in MB (default: 2048)
  CPUS         (optional)  CPU count (default: 2)
```

### Run Template

```bash
# With required variables
core vm run --template server-php --var SSH_KEY="$(cat ~/.ssh/id_rsa.pub)"

# With all variables
core vm run --template server-php \
  --var SSH_KEY="$(cat ~/.ssh/id_rsa.pub)" \
  --var DOMAIN=example.com \
  --var MEMORY=4096
```

## Template Locations

Templates are searched in order:

1. `.core/linuxkit/` - Project-specific templates
2. `~/.core/templates/` - User templates
3. Built-in templates

## Creating Templates

Create a LinuxKit YAML file in `.core/linuxkit/`:

### Development Template

`.core/linuxkit/dev.yml`:

```yaml
kernel:
  image: linuxkit/kernel:5.15
  cmdline: "console=tty0"

init:
  - linuxkit/init:v0.8
  - linuxkit/runc:v0.8
  - linuxkit/containerd:v0.8

onboot:
  - name: sysctl
    image: linuxkit/sysctl:v0.8
  - name: dhcpcd
    image: linuxkit/dhcpcd:v0.8
    command: ["/sbin/dhcpcd", "--nobackground", "-f", "/dhcpcd.conf"]

services:
  - name: sshd
    image: linuxkit/sshd:v0.8
  - name: docker
    image: docker:dind
    capabilities:
      - all
    binds:
      - /var/run:/var/run

files:
  - path: /etc/ssh/authorized_keys
    contents: |
      {{ .SSH_KEY }}
```

### Production Template

`.core/linuxkit/prod.yml`:

```yaml
kernel:
  image: linuxkit/kernel:5.15
  cmdline: "console=tty0 quiet"

init:
  - linuxkit/init:v0.8
  - linuxkit/runc:v0.8

onboot:
  - name: sysctl
    image: linuxkit/sysctl:v0.8
    binds:
      - /etc/sysctl.d:/etc/sysctl.d
  - name: dhcpcd
    image: linuxkit/dhcpcd:v0.8
    command: ["/sbin/dhcpcd", "--nobackground", "-f", "/dhcpcd.conf"]

services:
  - name: sshd
    image: linuxkit/sshd:v0.8
  - name: app
    image: myapp:{{ .VERSION }}
    capabilities:
      - CAP_NET_BIND_SERVICE
    binds:
      - /var/data:/data

files:
  - path: /etc/ssh/authorized_keys
    contents: |
      {{ .SSH_KEY }}
  - path: /etc/myapp/config.yaml
    contents: |
      server:
        port: 443
        domain: {{ .DOMAIN }}
      database:
        path: /data/app.db
```

Run with:

```bash
core vm run --template prod \
  --var SSH_KEY="$(cat ~/.ssh/id_rsa.pub)" \
  --var VERSION=1.2.3 \
  --var DOMAIN=example.com
```

## Template Variables

Variables use Go template syntax with double braces:

```yaml
# Required variable
contents: |
  {{ .SSH_KEY }}

# With default value
contents: |
  port: {{ .PORT | default "8080" }}

# Conditional
{{ if .DEBUG }}
  debug: true
{{ end }}
```

## See Also

- [LinuxKit VMs](linuxkit) - Running and managing VMs
- [Build Command](/build/cli/build/) - Building LinuxKit images
- [VM Command](/build/cli/vm/) - Full VM CLI reference
