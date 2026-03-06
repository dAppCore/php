# Service Contracts

The Service Contracts system provides a structured way to define SaaS services as first-class citizens in the framework. Services are the product layer - they define how modules are presented to users as SaaS products.

## Overview

Services in Core PHP are:

- **Discoverable** - Automatically found in configured module paths
- **Versioned** - Support semantic versioning with deprecation tracking
- **Dependency-aware** - Declare and validate dependencies on other services
- **Health-monitored** - Optional health checks for operational status

## Core Components

| Class | Purpose |
|-------|---------|
| `ServiceDefinition` | Interface for defining a service |
| `ServiceDiscovery` | Discovers and resolves services |
| `ServiceVersion` | Semantic versioning with deprecation |
| `ServiceDependency` | Declares service dependencies |
| `HealthCheckable` | Optional health monitoring |
| `HasServiceVersion` | Trait with default implementations |

## Creating a Service

### Basic Service Definition

Implement the `ServiceDefinition` interface to create a service:

```php
<?php

namespace Mod\Billing;

use Core\Service\Contracts\ServiceDefinition;
use Core\Service\Contracts\ServiceDependency;
use Core\Service\Concerns\HasServiceVersion;
use Core\Service\ServiceVersion;

class BillingService implements ServiceDefinition
{
    use HasServiceVersion;

    /**
     * Service metadata for the platform_services table.
     */
    public static function definition(): array
    {
        return [
            'code' => 'billing',                          // Unique identifier
            'module' => 'Mod\\Billing',                   // Module namespace
            'name' => 'Billing Service',                  // Display name
            'tagline' => 'Handle payments and invoices',  // Short description
            'description' => 'Complete billing solution with Stripe integration',
            'icon' => 'credit-card',                      // FontAwesome icon
            'color' => '#10B981',                         // Brand color (hex)
            'entitlement_code' => 'core.srv.billing',     // Access control
            'sort_order' => 20,                           // Menu ordering
        ];
    }

    /**
     * Declare dependencies on other services.
     */
    public static function dependencies(): array
    {
        return [
            ServiceDependency::required('auth', '>=1.0.0'),
            ServiceDependency::optional('analytics'),
        ];
    }

    /**
     * Admin menu items provided by this service.
     */
    public function menuItems(): array
    {
        return [
            [
                'label' => 'Billing',
                'icon' => 'credit-card',
                'route' => 'admin.billing.index',
                'order' => 20,
            ],
        ];
    }
}
```

### Definition Array Fields

| Field | Required | Type | Description |
|-------|----------|------|-------------|
| `code` | Yes | string | Unique service identifier (lowercase, alphanumeric) |
| `module` | Yes | string | Module namespace |
| `name` | Yes | string | Display name |
| `tagline` | No | string | Short description |
| `description` | No | string | Full description |
| `icon` | No | string | FontAwesome icon name |
| `color` | No | string | Hex color (e.g., `#3B82F6`) |
| `entitlement_code` | No | string | Access control entitlement |
| `sort_order` | No | int | Menu/display ordering |

## Service Versioning

Services use semantic versioning to track API compatibility and manage deprecation.

### Basic Versioning

```php
use Core\Service\ServiceVersion;

// Create version 2.1.0
$version = new ServiceVersion(2, 1, 0);
echo $version; // "2.1.0"

// Parse from string
$version = ServiceVersion::fromString('v2.1.0');

// Default version (1.0.0)
$version = ServiceVersion::initial();
```

### Semantic Versioning Rules

| Change | Version Bump | Description |
|--------|--------------|-------------|
| Major | 1.0.0 -> 2.0.0 | Breaking changes to the service contract |
| Minor | 1.0.0 -> 1.1.0 | New features, backwards compatible |
| Patch | 1.0.0 -> 1.0.1 | Bug fixes, backwards compatible |

### Implementing Custom Versions

Override the `version()` method from the trait:

```php
use Core\Service\ServiceVersion;
use Core\Service\Concerns\HasServiceVersion;

class MyService implements ServiceDefinition
{
    use HasServiceVersion;

    public static function version(): ServiceVersion
    {
        return new ServiceVersion(2, 3, 1);
    }
}
```

### Service Deprecation

Mark services as deprecated with migration guidance:

```php
public static function version(): ServiceVersion
{
    return (new ServiceVersion(1, 0, 0))
        ->deprecate(
            'Migrate to BillingV2 - see docs/migration.md',
            new \DateTimeImmutable('2026-06-01')
        );
}
```

### Deprecation Lifecycle

```
[Active] ──deprecate()──> [Deprecated] ──isPastSunset()──> [Sunset]
```

| State | Behavior |
|-------|----------|
| Active | Service fully operational |
| Deprecated | Works but logs warnings; consumers should migrate |
| Sunset | Past sunset date; may throw exceptions |

### Checking Deprecation Status

```php
$version = MyService::version();

// Check if deprecated
if ($version->deprecated) {
    echo $version->deprecationMessage;
    echo $version->sunsetDate->format('Y-m-d');
}

// Check if past sunset
if ($version->isPastSunset()) {
    throw new ServiceSunsetException('This service is no longer available');
}

// Version compatibility
$minimum = new ServiceVersion(1, 5, 0);
$current = new ServiceVersion(1, 8, 2);
$current->isCompatibleWith($minimum); // true (same major, >= minor.patch)
```

## Dependency Resolution

Services can declare dependencies on other services, and the framework resolves them automatically.

### Declaring Dependencies

```php
use Core\Service\Contracts\ServiceDependency;

public static function dependencies(): array
{
    return [
        // Required dependency - service fails if not available
        ServiceDependency::required('auth', '>=1.0.0'),

        // Optional dependency - service works with reduced functionality
        ServiceDependency::optional('analytics'),

        // Version range constraints
        ServiceDependency::required('billing', '>=2.0.0', '<3.0.0'),
    ];
}
```

### Version Constraints

| Constraint | Meaning |
|------------|---------|
| `>=1.0.0` | Minimum version 1.0.0 |
| `<3.0.0` | Maximum version below 3.0.0 |
| `>=2.0.0`, `<3.0.0` | Version 2.x only |
| `null` | Any version |

### Using ServiceDiscovery

```php
use Core\Service\ServiceDiscovery;

$discovery = app(ServiceDiscovery::class);

// Get all registered services
$services = $discovery->discover();

// Check if a service is available
if ($discovery->has('billing')) {
    $billingClass = $discovery->get('billing');
    $billing = $discovery->getInstance('billing');
}

// Get services in dependency order
$ordered = $discovery->getResolutionOrder();

// Validate all dependencies
$missing = $discovery->validateDependencies();
if (!empty($missing)) {
    foreach ($missing as $service => $deps) {
        logger()->error("Service {$service} missing: " . implode(', ', $deps));
    }
}
```

### Resolution Order

The framework uses topological sorting to resolve services in the correct order:

```php
// Services are resolved so dependencies come first
$ordered = $discovery->getResolutionOrder();
// Returns: ['auth', 'analytics', 'billing']
// (auth before billing if billing depends on auth)
```

### Handling Circular Dependencies

Circular dependencies are detected and throw `ServiceDependencyException`:

```php
use Core\Service\ServiceDependencyException;

try {
    $ordered = $discovery->getResolutionOrder();
} catch (ServiceDependencyException $e) {
    // Circular dependency: auth -> billing -> auth
    echo $e->getMessage();
    print_r($e->getDependencyChain());
}
```

## Manual Service Registration

Register services programmatically when auto-discovery is not desired:

```php
$discovery = app(ServiceDiscovery::class);

// Register with validation
$discovery->register(BillingService::class);

// Register without validation
$discovery->register(BillingService::class, validate: false);

// Add additional scan paths
$discovery->addPath(base_path('packages/my-package/src'));

// Clear discovery cache
$discovery->clearCache();
```

## Health Monitoring

Services can implement health checks for operational monitoring.

### Implementing HealthCheckable

```php
use Core\Service\Contracts\ServiceDefinition;
use Core\Service\Contracts\HealthCheckable;
use Core\Service\HealthCheckResult;

class BillingService implements ServiceDefinition, HealthCheckable
{
    // ... service definition methods ...

    public function healthCheck(): HealthCheckResult
    {
        try {
            $start = microtime(true);

            // Test critical dependencies
            $stripeConnected = $this->stripe->testConnection();

            $responseTime = (microtime(true) - $start) * 1000;

            if (!$stripeConnected) {
                return HealthCheckResult::unhealthy(
                    'Cannot connect to Stripe',
                    ['stripe_status' => 'disconnected']
                );
            }

            if ($responseTime > 1000) {
                return HealthCheckResult::degraded(
                    'Stripe responding slowly',
                    ['response_time_ms' => $responseTime],
                    responseTimeMs: $responseTime
                );
            }

            return HealthCheckResult::healthy(
                'All billing systems operational',
                ['stripe_status' => 'connected'],
                responseTimeMs: $responseTime
            );
        } catch (\Exception $e) {
            return HealthCheckResult::fromException($e);
        }
    }
}
```

### Health Check Result States

| Status | Method | Description |
|--------|--------|-------------|
| Healthy | `HealthCheckResult::healthy()` | Fully operational |
| Degraded | `HealthCheckResult::degraded()` | Working with reduced performance |
| Unhealthy | `HealthCheckResult::unhealthy()` | Not operational |
| Unknown | `HealthCheckResult::unknown()` | Status cannot be determined |

### Health Check Guidelines

- **Fast** - Complete within 5 seconds (preferably < 1 second)
- **Non-destructive** - Read-only operations only
- **Representative** - Test actual critical dependencies
- **Safe** - Catch all exceptions, return HealthCheckResult

### Aggregating Health Checks

```php
use Core\Service\Enums\ServiceStatus;

// Get all health check results
$results = [];
foreach ($discovery->discover() as $code => $class) {
    $instance = $discovery->getInstance($code);

    if ($instance instanceof HealthCheckable) {
        $results[$code] = $instance->healthCheck();
    }
}

// Determine overall status
$statuses = array_map(fn($r) => $r->status, $results);
$overall = ServiceStatus::worst($statuses);

if (!$overall->isOperational()) {
    // Alert on-call team
}
```

## Complete Example

Here is a complete service implementation with all features:

```php
<?php

namespace Mod\Blog;

use Core\Service\Contracts\ServiceDefinition;
use Core\Service\Contracts\ServiceDependency;
use Core\Service\Contracts\HealthCheckable;
use Core\Service\HealthCheckResult;
use Core\Service\ServiceVersion;

class BlogService implements ServiceDefinition, HealthCheckable
{
    public static function definition(): array
    {
        return [
            'code' => 'blog',
            'module' => 'Mod\\Blog',
            'name' => 'Blog',
            'tagline' => 'Content publishing platform',
            'description' => 'Full-featured blog with categories, tags, and comments',
            'icon' => 'newspaper',
            'color' => '#6366F1',
            'entitlement_code' => 'core.srv.blog',
            'sort_order' => 30,
        ];
    }

    public static function version(): ServiceVersion
    {
        return new ServiceVersion(2, 0, 0);
    }

    public static function dependencies(): array
    {
        return [
            ServiceDependency::required('auth', '>=1.0.0'),
            ServiceDependency::required('media', '>=1.0.0'),
            ServiceDependency::optional('seo'),
            ServiceDependency::optional('analytics'),
        ];
    }

    public function menuItems(): array
    {
        return [
            [
                'label' => 'Blog',
                'icon' => 'newspaper',
                'route' => 'admin.blog.index',
                'order' => 30,
                'children' => [
                    ['label' => 'Posts', 'route' => 'admin.blog.posts'],
                    ['label' => 'Categories', 'route' => 'admin.blog.categories'],
                    ['label' => 'Tags', 'route' => 'admin.blog.tags'],
                ],
            ],
        ];
    }

    public function healthCheck(): HealthCheckResult
    {
        try {
            $postsTable = \DB::table('posts')->exists();

            if (!$postsTable) {
                return HealthCheckResult::unhealthy('Posts table not found');
            }

            return HealthCheckResult::healthy('Blog service operational');
        } catch (\Exception $e) {
            return HealthCheckResult::fromException($e);
        }
    }
}
```

## Configuration

Configure service discovery in `config/core.php`:

```php
return [
    'services' => [
        // Enable/disable discovery caching
        'cache_discovery' => env('CORE_CACHE_SERVICES', true),

        // Cache TTL in seconds (default: 1 hour)
        'cache_ttl' => 3600,
    ],

    // Paths to scan for services
    'module_paths' => [
        app_path('Core'),
        app_path('Mod'),
        app_path('Website'),
        app_path('Plug'),
    ],
];
```

## Learn More

- [Module System](/core/modules)
- [Lifecycle Events](/core/events)
- [Seeder System](/core/seeder-system)
