# Docker

Push container images to Docker Hub, GitHub Container Registry, AWS ECR, or any OCI-compliant registry.

## Configuration

```yaml
publishers:
  - type: docker
    registry: ghcr.io
    image: org/myapp
```

## Options

| Option | Description | Default |
|--------|-------------|---------|
| `registry` | Registry hostname | `docker.io` |
| `image` | Image name | Project name |
| `platforms` | Target platforms | `linux/amd64` |
| `tags` | Image tags | `latest`, version |
| `dockerfile` | Dockerfile path | `Dockerfile` |
| `context` | Build context | `.` |

## Examples

### GitHub Container Registry

```yaml
publishers:
  - type: docker
    registry: ghcr.io
    image: host-uk/myapp
    platforms:
      - linux/amd64
      - linux/arm64
    tags:
      - latest
      - "{{ .Version }}"
      - "{{ .Major }}.{{ .Minor }}"
```

### Docker Hub

```yaml
publishers:
  - type: docker
    image: myorg/myapp
    tags:
      - latest
      - "{{ .Version }}"
```

### AWS ECR

```yaml
publishers:
  - type: docker
    registry: 123456789.dkr.ecr.eu-west-1.amazonaws.com
    image: myapp
```

### Multi-Platform Build

```yaml
publishers:
  - type: docker
    platforms:
      - linux/amd64
      - linux/arm64
      - linux/arm/v7
```

## Environment Variables

| Variable | Description |
|----------|-------------|
| `DOCKER_USERNAME` | Registry username |
| `DOCKER_PASSWORD` | Registry password or token |
| `AWS_ACCESS_KEY_ID` | AWS credentials (for ECR) |
| `AWS_SECRET_ACCESS_KEY` | AWS credentials (for ECR) |

## Tag Templates

| Template | Example |
|----------|---------|
| `.Version` | `1.2.3` |
| `.Major` | `1` |
| `.Minor` | `2` |
| `.Patch` | `3` |
| `.Major` + `.Minor` | `1.2` |

Templates use Go template syntax with double braces.
