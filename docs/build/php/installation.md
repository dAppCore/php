# Installation

This guide covers installing the Core PHP Framework in a new or existing Laravel application.

## Quick Start (Recommended)

The fastest way to get started is using the `core:new` command from any existing Core PHP installation:

```bash
php artisan core:new my-project
cd my-project
php artisan serve
```

This scaffolds a complete project with all Core packages pre-configured.

### Command Options

```bash
# Custom template
php artisan core:new my-api --template=host-uk/core-api-template

# Specific version
php artisan core:new my-app --branch=v1.0.0

# Skip automatic installation
php artisan core:new my-app --no-install

# Development mode (--prefer-source)
php artisan core:new my-app --dev

# Overwrite existing directory
php artisan core:new my-app --force
```

## From GitHub Template

You can also use the GitHub template directly:

1. Visit [host-uk/core-template](https://github.com/host-uk/core-template)
2. Click "Use this template"
3. Clone your new repository
4. Run `composer install && php artisan core:install`

## Manual Installation

For adding Core PHP to an existing Laravel project:

```bash
# Install Core PHP
composer require host-uk/core

# Install optional packages
composer require host-uk/core-admin  # Admin panel
composer require host-uk/core-api    # REST API
composer require host-uk/core-mcp    # MCP tools
```

## Existing Laravel Project

Add to an existing Laravel 11+ or 12 application:

```bash
composer require host-uk/core
```

The service provider will be auto-discovered.

## Package Installation

Install individual packages as needed:

### Core Package (Required)

```bash
composer require host-uk/core
```

Provides:
- Event-driven module system
- Actions pattern
- Multi-tenancy
- Activity logging
- Seeder auto-discovery

### Admin Package (Optional)

```bash
composer require host-uk/core-admin
```

Provides:
- Livewire admin panel
- Global search
- Service management UI
- Form components

**Additional requirements:**
```bash
composer require livewire/livewire:"^3.0|^4.0"
composer require livewire/flux:"^2.0"
```

### API Package (Optional)

```bash
composer require host-uk/core-api
```

Provides:
- OpenAPI/Swagger documentation
- Rate limiting
- Webhook signing
- Secure API keys

### MCP Package (Optional)

```bash
composer require host-uk/core-mcp
```

Provides:
- Model Context Protocol tools
- Tool analytics
- SQL query validation
- MCP playground UI

## Publishing Configuration

Publish configuration files:

```bash
# Publish core config
php artisan vendor:publish --tag=core-config

# Publish API config (if installed)
php artisan vendor:publish --tag=api-config

# Publish MCP config (if installed)
php artisan vendor:publish --tag=mcp-config
```

## Database Setup

Run migrations:

```bash
php artisan migrate
```

This creates tables for:
- Workspaces and users
- API keys (if core-api installed)
- MCP analytics (if core-mcp installed)
- Activity logs (if spatie/laravel-activitylog installed)

## Optional Dependencies

### Activity Logging

For activity logging features:

```bash
composer require spatie/laravel-activitylog:"^4.8"
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"
php artisan migrate
```

### Feature Flags

For feature flag support:

```bash
composer require laravel/pennant:"^1.0"
php artisan vendor:publish --provider="Laravel\Pennant\PennantServiceProvider"
php artisan migrate
```

## Verify Installation

Check that everything is installed correctly:

```bash
# Check installed packages
composer show | grep host-uk

# List available artisan commands
php artisan list make

# Should see:
# make:mod       Create a new module
# make:website   Create a new website module
# make:plug      Create a new plugin
```

## Environment Configuration

Add to your `.env`:

```env
# Core Configuration
CORE_MODULE_DISCOVERY=true
CORE_STRICT_WORKSPACE_MODE=true

# API Configuration (if using core-api)
API_DOCS_ENABLED=true
API_DOCS_REQUIRE_AUTH=false
API_RATE_LIMIT_DEFAULT=60

# MCP Configuration (if using core-mcp)
MCP_ANALYTICS_ENABLED=true
MCP_QUOTA_ENABLED=true
MCP_DATABASE_CONNECTION=readonly
```

## Directory Structure

After installation, your project structure will look like:

```
your-app/
├── app/
│   ├── Core/          # Core modules (framework-level)
│   ├── Mod/           # Feature modules (your code)
│   ├── Website/       # Website modules
│   └── Plug/          # Plugins
├── config/
│   ├── core.php       # Core configuration
│   ├── api.php        # API configuration (optional)
│   └── mcp.php        # MCP configuration (optional)
├── packages/          # Local package development (optional)
└── vendor/
    └── host-uk/       # Installed packages
```

## Next Steps

- [Configuration →](./configuration)
- [Quick Start →](./quick-start)
- [Create Your First Module →](./quick-start#creating-a-module)

## Troubleshooting

### Service Provider Not Discovered

If the service provider isn't auto-discovered:

```bash
composer dump-autoload
php artisan package:discover --ansi
```

### Migration Errors

If migrations fail:

```bash
# Check database connection
php artisan db:show

# Run migrations with verbose output
php artisan migrate --verbose
```

### Module Discovery Issues

If modules aren't being discovered:

```bash
# Clear application cache
php artisan optimize:clear

# Verify module paths in config/core.php
php artisan config:show core.module_paths
```

## Minimum Requirements

- PHP 8.2+
- Laravel 11.0+ or 12.0+
- MySQL 8.0+ / PostgreSQL 13+ / SQLite 3.35+
- Composer 2.0+
- 128MB PHP memory limit (256MB recommended)
