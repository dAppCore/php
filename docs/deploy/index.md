# Deploy

Deploy applications to VMs, containers, and cloud infrastructure.

## Deployment Options

| Target | Description | Use Case |
|--------|-------------|----------|
| [PHP](php) | Laravel/PHP with FrankenPHP | Web applications, APIs |
| [LinuxKit](linuxkit) | Lightweight immutable VMs | Production servers, edge nodes |
| [Templates](templates) | Pre-configured VM images | Quick deployment, dev environments |
| [Docker](docker) | Container orchestration | Kubernetes, Swarm, ECS |

## Quick Start

### Run a Production Server

```bash
# Build and run from template
core vm run --template server-php --var SSH_KEY="$(cat ~/.ssh/id_rsa.pub)"

# Or run a pre-built image
core vm run -d --memory 4096 --cpus 4 server.iso
```

### Deploy to Docker

```bash
# Build and push image
core build --type docker --image ghcr.io/myorg/myapp --push

# Deploy with docker-compose
docker compose up -d
```

## Architecture

```
┌─────────────────────────────────────────────────────────┐
│                    Build Phase                          │
│  core build → Docker images, LinuxKit ISOs, binaries   │
└─────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────┐
│                   Publish Phase                         │
│  core ci → GitHub, Docker Hub, GHCR, Homebrew, etc.    │
└─────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────┐
│                   Deploy Phase                          │
│  core vm → LinuxKit VMs, templates, orchestration      │
└─────────────────────────────────────────────────────────┘
```

## CLI Commands

| Command | Description |
|---------|-------------|
| `core vm run` | Run a LinuxKit image or template |
| `core vm ps` | List running VMs |
| `core vm stop` | Stop a VM |
| `core vm logs` | View VM logs |
| `core vm exec` | Execute command in VM |
| `core vm templates` | Manage LinuxKit templates |

See the [CLI Reference](/build/cli/vm/) for full command documentation.
