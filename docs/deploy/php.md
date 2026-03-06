# PHP Deployment

Deploy Laravel/PHP applications using FrankenPHP containers, LinuxKit VMs, or Coolify.

## Quick Start

```bash
# Build production image
core php build --name myapp --tag v1.0

# Run locally
core php serve --name myapp -d

# Deploy to Coolify
core php deploy --wait
```

## Building Images

### Docker Image

Build a production-ready Docker image with FrankenPHP:

```bash
# Basic build
core php build

# With custom name and tag
core php build --name myapp --tag v1.0

# For specific platform
core php build --name myapp --platform linux/amd64

# Without cache
core php build --name myapp --no-cache
```

### LinuxKit Image

Build a bootable VM image:

```bash
# Build with default template (server-php)
core php build --type linuxkit

# Build QCOW2 for QEMU/KVM
core php build --type linuxkit --format qcow2

# Build ISO for bare metal
core php build --type linuxkit --format iso

# Custom output path
core php build --type linuxkit --output ./dist/server.qcow2
```

### Build Options

| Flag | Description | Default |
|------|-------------|---------|
| `--type` | Build type: `docker` or `linuxkit` | docker |
| `--name` | Image name | project directory |
| `--tag` | Image tag | latest |
| `--platform` | Target platform | linux/amd64 |
| `--dockerfile` | Custom Dockerfile path | Dockerfile |
| `--format` | LinuxKit format: qcow2, iso, raw, vmdk | qcow2 |
| `--template` | LinuxKit template | server-php |
| `--no-cache` | Build without cache | false |

## Running Production Containers

### Local Testing

Run a production container locally before deploying:

```bash
# Run in foreground
core php serve --name myapp

# Run detached (background)
core php serve --name myapp -d

# Custom ports
core php serve --name myapp --port 8080 --https-port 8443

# With environment file
core php serve --name myapp --env-file .env.production
```

### Shell Access

Debug running containers:

```bash
# Open shell in container
core php shell <container-id>

# Run artisan commands
docker exec -it <container-id> php artisan migrate:status
```

## Deploying to Coolify

[Coolify](https://coolify.io) is a self-hosted PaaS for deploying applications.

### Configuration

Add Coolify credentials to `.env`:

```env
COOLIFY_URL=https://coolify.example.com
COOLIFY_TOKEN=your-api-token
COOLIFY_APP_ID=production-app-id
COOLIFY_STAGING_APP_ID=staging-app-id
```

Or configure in `.core/php.yaml`:

```yaml
version: 1

deploy:
  coolify:
    server: https://coolify.example.com
    project: my-project
```

### Deploy Commands

```bash
# Deploy to production
core php deploy

# Deploy to staging
core php deploy --staging

# Force deploy (even if no changes)
core php deploy --force

# Wait for deployment to complete
core php deploy --wait
```

### Check Status

```bash
# Current deployment status
core php deploy:status

# Staging status
core php deploy:status --staging

# Specific deployment
core php deploy:status --id abc123
```

### Rollback

```bash
# Rollback to previous deployment
core php deploy:rollback

# Rollback staging
core php deploy:rollback --staging

# Rollback to specific deployment
core php deploy:rollback --id abc123

# Wait for rollback to complete
core php deploy:rollback --wait
```

### Deployment History

```bash
# List recent deployments
core php deploy:list

# Staging deployments
core php deploy:list --staging

# Show more
core php deploy:list --limit 20
```

## CI/CD Pipeline

### GitHub Actions

```yaml
name: Deploy

on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Setup Core CLI
        run: |
          curl -fsSL https://get.host.uk.com | bash
          echo "$HOME/.core/bin" >> $GITHUB_PATH

      - name: Build image
        run: core php build --name ${{ vars.IMAGE_NAME }} --tag ${{ github.sha }}

      - name: Push to registry
        run: |
          echo "${{ secrets.GITHUB_TOKEN }}" | docker login ghcr.io -u ${{ github.actor }} --password-stdin
          docker push ${{ vars.IMAGE_NAME }}:${{ github.sha }}

      - name: Deploy
        env:
          COOLIFY_URL: ${{ secrets.COOLIFY_URL }}
          COOLIFY_TOKEN: ${{ secrets.COOLIFY_TOKEN }}
          COOLIFY_APP_ID: ${{ secrets.COOLIFY_APP_ID }}
        run: core php deploy --wait
```

### GitLab CI

```yaml
deploy:
  stage: deploy
  image: hostuk/core:latest
  script:
    - core php build --name $CI_REGISTRY_IMAGE --tag $CI_COMMIT_SHA
    - docker login -u $CI_REGISTRY_USER -p $CI_REGISTRY_PASSWORD $CI_REGISTRY
    - docker push $CI_REGISTRY_IMAGE:$CI_COMMIT_SHA
    - core php deploy --wait
  only:
    - main
```

## Environment Configuration

### Production .env

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://myapp.com

# Database
DB_CONNECTION=pgsql
DB_HOST=db.internal
DB_DATABASE=myapp
DB_USERNAME=myapp
DB_PASSWORD=${DB_PASSWORD}

# Cache & Queue
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
REDIS_HOST=redis.internal

# FrankenPHP
OCTANE_SERVER=frankenphp
OCTANE_WORKERS=auto
OCTANE_MAX_REQUESTS=1000
```

### Health Checks

Ensure your app has a health endpoint:

```php
// routes/web.php
Route::get('/health', fn () => response()->json([
    'status' => 'ok',
    'timestamp' => now()->toIso8601String(),
]));
```

## Deployment Strategies

### Blue-Green

```bash
# Build new version
core php build --name myapp --tag v2.0

# Deploy to staging
core php deploy --staging --wait

# Test staging
curl https://staging.myapp.com/health

# Switch production
core php deploy --wait
```

### Canary

```bash
# Deploy to canary (10% traffic)
COOLIFY_APP_ID=$CANARY_APP_ID core php deploy --wait

# Monitor metrics, then full rollout
core php deploy --wait
```

## See Also

- [Docker Deployment](docker) - Container orchestration
- [LinuxKit VMs](linuxkit) - VM-based deployment
- [Templates](templates) - Pre-configured VM templates
- [PHP CLI Reference](/build/cli/php/) - Full command documentation
