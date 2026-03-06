# Docker Deployment

Deploy containerised applications with Docker, Docker Compose, and container orchestrators.

## Building Images

Build Docker images with `core build`:

```bash
# Auto-detect Dockerfile and build
core build --type docker

# Custom image name
core build --type docker --image ghcr.io/myorg/myapp

# Build and push to registry
core build --type docker --image ghcr.io/myorg/myapp --push
```

## Docker Compose

### Basic Setup

`docker-compose.yml`:

```yaml
version: '3.8'

services:
  app:
    image: ghcr.io/myorg/myapp:latest
    ports:
      - "8080:8080"
    environment:
      - APP_ENV=production
      - DATABASE_URL=postgres://db:5432/myapp
    depends_on:
      - db
      - redis

  db:
    image: postgres:15
    volumes:
      - postgres_data:/var/lib/postgresql/data
    environment:
      - POSTGRES_DB=myapp
      - POSTGRES_PASSWORD_FILE=/run/secrets/db_password
    secrets:
      - db_password

  redis:
    image: redis:7-alpine
    volumes:
      - redis_data:/data

volumes:
  postgres_data:
  redis_data:

secrets:
  db_password:
    file: ./secrets/db_password.txt
```

### Deploy

```bash
# Start services
docker compose up -d

# View logs
docker compose logs -f app

# Scale horizontally
docker compose up -d --scale app=3

# Update to new version
docker compose pull && docker compose up -d
```

## Multi-Stage Builds

Optimised Dockerfile for PHP applications:

```dockerfile
# Build stage
FROM composer:2 AS deps
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --prefer-dist

# Production stage
FROM dunglas/frankenphp:latest
WORKDIR /app

COPY --from=deps /app/vendor ./vendor
COPY . .

RUN composer dump-autoload --optimize

EXPOSE 8080
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
```

## Health Checks

Add health checks for orchestrator integration:

```dockerfile
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
  CMD curl -f http://localhost:8080/health || exit 1
```

Or in docker-compose:

```yaml
services:
  app:
    image: myapp:latest
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:8080/health"]
      interval: 30s
      timeout: 3s
      retries: 3
      start_period: 5s
```

## Environment Configuration

### Using .env Files

```yaml
services:
  app:
    image: myapp:latest
    env_file:
      - .env
      - .env.local
```

### Environment Variables

| Variable | Description |
|----------|-------------|
| `APP_ENV` | Environment (production, staging) |
| `APP_DEBUG` | Enable debug mode |
| `DATABASE_URL` | Database connection string |
| `REDIS_URL` | Redis connection string |
| `LOG_LEVEL` | Logging verbosity |

## Registry Authentication

### GitHub Container Registry

```bash
# Login
echo $GITHUB_TOKEN | docker login ghcr.io -u USERNAME --password-stdin

# Push
docker push ghcr.io/myorg/myapp:latest
```

### AWS ECR

```bash
# Login
aws ecr get-login-password --region eu-west-1 | \
  docker login --username AWS --password-stdin 123456789.dkr.ecr.eu-west-1.amazonaws.com

# Push
docker push 123456789.dkr.ecr.eu-west-1.amazonaws.com/myapp:latest
```

## Orchestration

### Docker Swarm

```bash
# Initialise swarm
docker swarm init

# Deploy stack
docker stack deploy -c docker-compose.yml myapp

# Scale service
docker service scale myapp_app=5

# Rolling update
docker service update --image myapp:v2 myapp_app
```

### Kubernetes

Generate Kubernetes manifests from Compose:

```bash
# Using kompose
kompose convert -f docker-compose.yml

# Apply to cluster
kubectl apply -f .
```

## See Also

- [Docker Publisher](/publish/docker) - Push images to registries
- [Build Command](/build/cli/build/) - Build Docker images
- [LinuxKit](linuxkit) - VM-based deployment
