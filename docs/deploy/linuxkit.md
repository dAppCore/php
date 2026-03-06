# LinuxKit VMs

Deploy applications using lightweight, immutable LinuxKit VMs. VMs run using QEMU or HyperKit depending on your system.

## Running VMs

### From Image File

Run pre-built images in `.iso`, `.qcow2`, `.vmdk`, or `.raw` format:

```bash
# Run ISO image
core vm run server.iso

# Run with more resources
core vm run -d --memory 4096 --cpus 4 server.qcow2

# Custom SSH port
core vm run --ssh-port 2223 server.iso
```

### From Template

Build and run from a LinuxKit template in one command:

```bash
# Run template with SSH key
core vm run --template server-php --var SSH_KEY="$(cat ~/.ssh/id_rsa.pub)"

# Multiple variables
core vm run --template server-php \
  --var SSH_KEY="$(cat ~/.ssh/id_rsa.pub)" \
  --var DOMAIN=example.com
```

## Options

| Flag | Description | Default |
|------|-------------|---------|
| `--template` | Run from a LinuxKit template | - |
| `--var` | Template variable (KEY=VALUE) | - |
| `--name` | VM name | auto |
| `--memory` | Memory in MB | 1024 |
| `--cpus` | CPU count | 1 |
| `--ssh-port` | SSH port for exec | 2222 |
| `-d` | Detached mode (background) | false |

## Managing VMs

### List Running VMs

```bash
# Show running VMs
core vm ps

# Include stopped VMs
core vm ps -a
```

Output:
```
ID        NAME      IMAGE                STATUS    STARTED   PID
abc12345  myvm      server-php.qcow2     running   5m        12345
def67890  devbox    core-dev.iso         stopped   2h        -
```

### Stop a VM

```bash
# Full ID
core vm stop abc12345678

# Partial ID match
core vm stop abc1
```

### View Logs

```bash
# View logs
core vm logs abc12345

# Follow logs (like tail -f)
core vm logs -f abc12345
```

### Execute Commands

Run commands in a VM via SSH:

```bash
# List files
core vm exec abc12345 ls -la

# Check services
core vm exec abc12345 systemctl status php-fpm

# Open interactive shell
core vm exec abc12345 /bin/sh
```

## Building Images

Build LinuxKit images with `core build`:

```bash
# Build ISO from config
core build --type linuxkit --config .core/linuxkit/server.yml

# Build QCOW2 for QEMU/KVM
core build --type linuxkit --config .core/linuxkit/server.yml --format qcow2-bios

# Build for multiple platforms
core build --type linuxkit --targets linux/amd64,linux/arm64
```

### Output Formats

| Format | Description | Use Case |
|--------|-------------|----------|
| `iso-bios` | Bootable ISO | Physical servers, legacy VMs |
| `qcow2-bios` | QEMU/KVM image | Linux hypervisors |
| `raw` | Raw disk image | Cloud providers |
| `vmdk` | VMware image | VMware ESXi |
| `vhd` | Hyper-V image | Windows Server |

## LinuxKit Configuration

Example `.core/linuxkit/server.yml`:

```yaml
kernel:
  image: linuxkit/kernel:5.15
  cmdline: "console=tty0"

init:
  - linuxkit/init:v0.8
  - linuxkit/runc:v0.8

onboot:
  - name: sysctl
    image: linuxkit/sysctl:v0.8
  - name: dhcpcd
    image: linuxkit/dhcpcd:v0.8
    command: ["/sbin/dhcpcd", "--nobackground", "-f", "/dhcpcd.conf"]

services:
  - name: sshd
    image: linuxkit/sshd:v0.8
  - name: php
    image: dunglas/frankenphp:latest

files:
  - path: /etc/ssh/authorized_keys
    contents: |
      {{ .SSH_KEY }}
  - path: /etc/myapp/config.yaml
    contents: |
      server:
        port: 8080
        domain: {{ .DOMAIN }}
```

## See Also

- [Templates](templates) - Pre-configured VM templates
- [LinuxKit Publisher](/publish/linuxkit) - Publish LinuxKit images
- [CLI Reference](/build/cli/vm/) - Full VM command documentation
