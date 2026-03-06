# LinuxKit

Build and publish bootable Linux images for VMs, bare metal, and cloud platforms.

## Configuration

```yaml
publishers:
  - type: linuxkit
    config: .core/linuxkit/server.yml
```

## Options

| Option | Description | Default |
|--------|-------------|---------|
| `config` | LinuxKit YAML config | Required |
| `formats` | Output formats | `[iso]` |
| `platforms` | Target platforms | `linux/amd64` |
| `name` | Image name | From config |

## Formats

| Format | Description |
|--------|-------------|
| `iso` | Bootable ISO image |
| `qcow2` | QEMU/KVM image |
| `raw` | Raw disk image |
| `vhd` | Hyper-V image |
| `vmdk` | VMware image |
| `aws` | AWS AMI |
| `gcp` | Google Cloud image |
| `azure` | Azure VHD |

## Examples

### ISO + QCOW2

```yaml
publishers:
  - type: linuxkit
    config: .core/linuxkit/server.yml
    formats:
      - iso
      - qcow2
    platforms:
      - linux/amd64
      - linux/arm64
```

### Cloud Images

```yaml
publishers:
  - type: linuxkit
    config: .core/linuxkit/cloud.yml
    formats:
      - aws
      - gcp
      - azure
```

### Multiple Configurations

```yaml
publishers:
  - type: linuxkit
    config: .core/linuxkit/minimal.yml
    formats: [iso]
    name: myapp-minimal

  - type: linuxkit
    config: .core/linuxkit/full.yml
    formats: [iso, qcow2]
    name: myapp-full
```

## LinuxKit Config

`.core/linuxkit/server.yml`:

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
  - name: myapp
    image: myapp:latest

files:
  - path: /etc/myapp/config.yaml
    contents: |
      server:
        port: 8080
```

## Environment Variables

| Variable | Description |
|----------|-------------|
| `AWS_ACCESS_KEY_ID` | AWS credentials (for AMI publishing) |
| `AWS_SECRET_ACCESS_KEY` | AWS credentials |
| `GOOGLE_APPLICATION_CREDENTIALS` | GCP credentials (for GCP publishing) |
| `AZURE_CREDENTIALS` | Azure credentials (for Azure publishing) |