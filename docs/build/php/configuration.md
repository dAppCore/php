# Configuration Management

Core PHP Framework provides a powerful multi-profile configuration system with versioning, rollback capabilities, and environment-specific overrides.

## Basic Usage

### Storing Configuration

```php
use Core\Config\ConfigService;

$config = app(ConfigService::class);

// Store simple value
$config->set('app.name', 'My Application');

// Store nested configuration
$config->set('mail.driver', 'smtp', [
    'host' => 'smtp.mailtrap.io',
    'port' => 2525,
    'encryption' => 'tls',
]);

// Store with profile
$config->set('cache.driver', 'redis', [], 'production');
```

### Retrieving Configuration

```php
// Get simple value
$name = $config->get('app.name');

// Get with default
$driver = $config->get('cache.driver', 'file');

// Get nested value
$host = $config->get('mail.driver.host');

// Get from specific profile
$driver = $config->get('cache.driver', 'file', 'production');
```

## Profiles

Profiles enable environment-specific configuration:

### Creating Profiles

```php
use Core\Config\Models\ConfigProfile;

// Development profile
$dev = ConfigProfile::create([
    'name' => 'development',
    'description' => 'Development environment settings',
    'is_active' => true,
]);

// Staging profile
$staging = ConfigProfile::create([
    'name' => 'staging',
    'description' => 'Staging environment',
    'is_active' => false,
]);

// Production profile
$prod = ConfigProfile::create([
    'name' => 'production',
    'description' => 'Production environment',
    'is_active' => false,
]);
```

### Activating Profiles

```php
// Activate production profile
$prod->activate();

// Deactivate all others
ConfigProfile::query()
    ->where('id', '!=', $prod->id)
    ->update(['is_active' => false]);
```

### Profile Inheritance

```php
// Set base value
$config->set('cache.ttl', 3600);

// Override in production
$config->set('cache.ttl', 86400, [], 'production');

// Override in development
$config->set('cache.ttl', 60, [], 'development');

// Retrieval uses active profile automatically
$ttl = $config->get('cache.ttl'); // Returns profile-specific value
```

## Configuration Keys

### Key Metadata

```php
use Core\Config\Models\ConfigKey;

$key = ConfigKey::create([
    'key' => 'api.rate_limit',
    'description' => 'API rate limit per hour',
    'type' => 'integer',
    'is_sensitive' => false,
    'validation_rules' => ['required', 'integer', 'min:100'],
]);
```

### Sensitive Configuration

```php
// Mark as sensitive (encrypted at rest)
$key = ConfigKey::create([
    'key' => 'payment.stripe.secret',
    'is_sensitive' => true,
]);

// Set sensitive value (auto-encrypted)
$config->set('payment.stripe.secret', 'sk_live_...');

// Retrieve (auto-decrypted)
$secret = $config->get('payment.stripe.secret');
```

### Validation

```php
// Validation runs automatically
try {
    $config->set('api.rate_limit', 'invalid'); // Throws ValidationException
} catch (ValidationException $e) {
    // Handle validation error
}

// Valid value
$config->set('api.rate_limit', 1000); // ✅ Passes validation
```

## Versioning

Track configuration changes with automatic versioning:

### Creating Versions

```php
use Core\Config\ConfigVersioning;

$versioning = app(ConfigVersioning::class);

// Create snapshot
$version = $versioning->createVersion('production', [
    'description' => 'Pre-deployment snapshot',
    'created_by' => auth()->id(),
]);
```

### Viewing Versions

```php
use Core\Config\Models\ConfigVersion;

// List all versions
$versions = ConfigVersion::query()
    ->where('profile', 'production')
    ->orderByDesc('created_at')
    ->get();

// Get specific version
$version = ConfigVersion::find($id);

// View snapshot
$snapshot = $version->snapshot; // ['cache.driver' => 'redis', ...]
```

### Rolling Back

```php
// Rollback to previous version
$versioning->rollback($version->id);

// Rollback with confirmation
if ($version->created_at->isToday()) {
    $versioning->rollback($version->id);
}
```

### Comparing Versions

```php
use Core\Config\VersionDiff;

$diff = app(VersionDiff::class);

// Compare two versions
$changes = $diff->compare($oldVersion, $newVersion);

// Output:
[
    'added' => ['cache.prefix' => 'app_'],
    'modified' => ['cache.ttl' => ['old' => 3600, 'new' => 7200]],
    'removed' => ['cache.legacy_driver'],
]
```

## Import & Export

### Exporting Configuration

```php
use Core\Config\ConfigExporter;

$exporter = app(ConfigExporter::class);

// Export active profile
$json = $exporter->export();

// Export specific profile
$json = $exporter->export('production');

// Export with metadata
$json = $exporter->export('production', [
    'include_sensitive' => false, // Exclude secrets
    'include_metadata' => true,   // Include descriptions
]);
```

**Export Format:**

```json
{
  "profile": "production",
  "exported_at": "2026-01-26T12:00:00Z",
  "config": {
    "cache.driver": {
      "value": "redis",
      "description": "Cache driver",
      "type": "string"
    },
    "cache.ttl": {
      "value": 86400,
      "description": "Cache TTL in seconds",
      "type": "integer"
    }
  }
}
```

### Importing Configuration

```php
use Core\Config\ConfigService;

$config = app(ConfigService::class);

// Import from JSON
$result = $config->import($json, 'production');

// Import with merge strategy
$result = $config->import($json, 'production', [
    'merge' => true,        // Merge with existing
    'overwrite' => false,   // Don't overwrite existing
    'validate' => true,     // Validate before import
]);
```

**Import Result:**

```php
use Core\Config\ImportResult;

$result->imported;  // ['cache.driver', 'cache.ttl']
$result->skipped;   // ['cache.legacy']
$result->failed;    // ['cache.invalid' => 'Validation failed']
```

### Console Commands

```bash
# Export configuration
php artisan config:export production --output=config.json

# Import configuration
php artisan config:import config.json --profile=staging

# Create version snapshot
php artisan config:version production --message="Pre-deployment"
```

## Configuration Providers

Create reusable configuration providers:

```php
<?php

namespace Mod\Blog\Config;

use Core\Config\Contracts\ConfigProvider;

class BlogConfigProvider implements ConfigProvider
{
    public function provide(): array
    {
        return [
            'blog.posts_per_page' => [
                'value' => 10,
                'description' => 'Posts per page',
                'type' => 'integer',
                'validation' => ['required', 'integer', 'min:1'],
            ],
            'blog.allow_comments' => [
                'value' => true,
                'description' => 'Enable comments',
                'type' => 'boolean',
            ],
        ];
    }
}
```

**Register Provider:**

```php
use Core\Events\FrameworkBooted;

public function onFrameworkBooted(FrameworkBooted $event): void
{
    $config = app(ConfigService::class);
    $config->register(new BlogConfigProvider());
}
```

## Caching

Configuration is cached for performance:

```php
// Clear config cache
$config->invalidate();

// Clear specific key cache
$config->invalidate('cache.driver');

// Rebuild cache
$config->rebuild();
```

**Cache Strategy:**
- Uses `remember()` with 1-hour TTL
- Invalidated on config changes
- Per-profile cache keys
- Tagged for easy clearing

## Events

Configuration changes fire events:

```php
use Core\Config\Events\ConfigChanged;
use Core\Config\Events\ConfigInvalidated;

// Listen for changes
Event::listen(ConfigChanged::class, function ($event) {
    Log::info('Config changed', [
        'key' => $event->key,
        'old' => $event->oldValue,
        'new' => $event->newValue,
    ]);
});

// Listen for cache invalidation
Event::listen(ConfigInvalidated::class, function ($event) {
    // Rebuild dependent caches
});
```

## Best Practices

### 1. Use Profiles for Environments

```php
// ✅ Good - environment-specific
$config->set('cache.driver', 'redis', [], 'production');
$config->set('cache.driver', 'array', [], 'testing');

// ❌ Bad - single value for all environments
$config->set('cache.driver', 'redis');
```

### 2. Mark Sensitive Data

```php
// ✅ Good - encrypted at rest
ConfigKey::create([
    'key' => 'payment.api_key',
    'is_sensitive' => true,
]);

// ❌ Bad - plaintext secrets
$config->set('payment.api_key', 'secret123');
```

### 3. Version Before Changes

```php
// ✅ Good - create snapshot first
$versioning->createVersion('production', [
    'description' => 'Pre-cache-driver-change',
]);
$config->set('cache.driver', 'redis', [], 'production');

// ❌ Bad - no rollback point
$config->set('cache.driver', 'redis', [], 'production');
```

### 4. Validate Configuration

```php
// ✅ Good - validation rules
ConfigKey::create([
    'key' => 'api.rate_limit',
    'validation_rules' => ['required', 'integer', 'min:100', 'max:10000'],
]);

// ❌ Bad - no validation
$config->set('api.rate_limit', 'unlimited'); // Invalid!
```

## Testing Configuration

```php
use Tests\TestCase;
use Core\Config\ConfigService;

class ConfigTest extends TestCase
{
    public function test_stores_configuration(): void
    {
        $config = app(ConfigService::class);

        $config->set('test.key', 'value');

        $this->assertEquals('value', $config->get('test.key'));
    }

    public function test_profile_isolation(): void
    {
        $config = app(ConfigService::class);

        $config->set('cache.driver', 'redis', [], 'production');
        $config->set('cache.driver', 'array', [], 'testing');

        // Activate testing profile
        ConfigProfile::where('name', 'testing')->first()->activate();

        $this->assertEquals('array', $config->get('cache.driver'));
    }
}
```

## Learn More

- [Module System →](/core/modules)
- [Multi-Tenancy →](/core/tenancy)
