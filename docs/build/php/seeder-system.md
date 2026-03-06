# Seeder System

The Seeder System provides automatic discovery, dependency resolution, and ordered execution of database seeders across modules. It supports both auto-discovery and manual registration with explicit priority and dependency declarations.

## Overview

The Core seeder system offers:

- **Auto-discovery** - Finds seeders in module directories automatically
- **Dependency ordering** - Seeders run in dependency-resolved order
- **Priority control** - Fine-grained control over execution order
- **Circular detection** - Catches and reports circular dependencies
- **Filtering** - Include/exclude seeders at runtime

## Core Components

| Class | Purpose |
|-------|---------|
| `SeederDiscovery` | Auto-discovers and orders seeders |
| `SeederRegistry` | Manual seeder registration |
| `CoreDatabaseSeeder` | Base seeder with discovery support |
| `#[SeederPriority]` | Attribute for priority |
| `#[SeederAfter]` | Attribute for dependencies |
| `#[SeederBefore]` | Attribute for reverse dependencies |
| `CircularDependencyException` | Thrown on circular deps |

## Discovery

Seeders are auto-discovered in `Database/Seeders/` directories within configured module paths.

### Discovery Pattern

```
{module_path}/*/Database/Seeders/*Seeder.php
```

For example, with module paths `[app_path('Mod')]`:

```
app/Mod/Blog/Database/Seeders/PostSeeder.php      // Discovered
app/Mod/Blog/Database/Seeders/CategorySeeder.php  // Discovered
app/Mod/Auth/Database/Seeders/UserSeeder.php      // Discovered
```

### Using SeederDiscovery

```php
use Core\Database\Seeders\SeederDiscovery;

$discovery = new SeederDiscovery([
    app_path('Core'),
    app_path('Mod'),
]);

// Get ordered seeders
$seeders = $discovery->discover();
// Returns: ['UserSeeder', 'CategorySeeder', 'PostSeeder', ...]
```

## Priority System

Seeders declare priority using the `#[SeederPriority]` attribute or a public `$priority` property. Lower priority values run first.

### Using the Attribute

```php
use Core\Database\Seeders\Attributes\SeederPriority;
use Illuminate\Database\Seeder;

#[SeederPriority(10)]
class FeatureSeeder extends Seeder
{
    public function run(): void
    {
        // Runs early (priority 10)
    }
}

#[SeederPriority(90)]
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // Runs later (priority 90)
    }
}
```

### Using a Property

```php
class FeatureSeeder extends Seeder
{
    public int $priority = 10;

    public function run(): void
    {
        // Runs early
    }
}
```

### Priority Guidelines

| Range | Use Case | Examples |
|-------|----------|----------|
| 0-20 | Foundation data | Features, configuration, settings |
| 20-40 | Core data | Packages, plans, workspaces |
| 40-60 | Default (50) | General module seeders |
| 60-80 | Content data | Pages, posts, products |
| 80-100 | Demo/test data | Sample content, test users |

## Dependency Resolution

Dependencies ensure seeders run in the correct order regardless of priority. Dependencies take precedence over priority.

### Using #[SeederAfter]

Declare that this seeder must run after specified seeders:

```php
use Core\Database\Seeders\Attributes\SeederAfter;
use Mod\Feature\Database\Seeders\FeatureSeeder;

#[SeederAfter(FeatureSeeder::class)]
class PackageSeeder extends Seeder
{
    public function run(): void
    {
        // Runs after FeatureSeeder
    }
}
```

### Multiple Dependencies

```php
use Mod\Feature\Database\Seeders\FeatureSeeder;
use Mod\Tenant\Database\Seeders\TenantSeeder;

#[SeederAfter(FeatureSeeder::class, TenantSeeder::class)]
class WorkspaceSeeder extends Seeder
{
    public function run(): void
    {
        // Runs after both FeatureSeeder and TenantSeeder
    }
}
```

### Using #[SeederBefore]

Declare that this seeder must run before specified seeders. This is the inverse relationship - you're saying other seeders depend on this one:

```php
use Core\Database\Seeders\Attributes\SeederBefore;
use Mod\Package\Database\Seeders\PackageSeeder;

#[SeederBefore(PackageSeeder::class)]
class FeatureSeeder extends Seeder
{
    public function run(): void
    {
        // Runs before PackageSeeder
    }
}
```

### Using Properties

As an alternative to attributes, use public properties:

```php
class WorkspaceSeeder extends Seeder
{
    public array $after = [
        FeatureSeeder::class,
        PackageSeeder::class,
    ];

    public array $before = [
        DemoSeeder::class,
    ];

    public function run(): void
    {
        // ...
    }
}
```

## Complex Ordering Examples

### Example 1: Linear Chain

```php
// Run order: Feature -> Package -> Workspace -> User

#[SeederPriority(10)]
class FeatureSeeder extends Seeder { }

#[SeederAfter(FeatureSeeder::class)]
class PackageSeeder extends Seeder { }

#[SeederAfter(PackageSeeder::class)]
class WorkspaceSeeder extends Seeder { }

#[SeederAfter(WorkspaceSeeder::class)]
class UserSeeder extends Seeder { }
```

### Example 2: Diamond Dependency

```php
//        Feature
//       /       \
//   Package    Plan
//       \       /
//       Workspace

#[SeederPriority(10)]
class FeatureSeeder extends Seeder { }

#[SeederAfter(FeatureSeeder::class)]
class PackageSeeder extends Seeder { }

#[SeederAfter(FeatureSeeder::class)]
class PlanSeeder extends Seeder { }

#[SeederAfter(PackageSeeder::class, PlanSeeder::class)]
class WorkspaceSeeder extends Seeder { }

// Execution order: Feature -> [Package, Plan] -> Workspace
// Package and Plan can run in either order (same priority level)
```

### Example 3: Priority with Dependencies

```php
// Dependencies override priority

#[SeederPriority(90)]  // High priority number (normally runs late)
#[SeederBefore(DemoSeeder::class)]
class FeatureSeeder extends Seeder { }

#[SeederPriority(10)]  // Low priority number (normally runs early)
#[SeederAfter(FeatureSeeder::class)]
class DemoSeeder extends Seeder { }

// Despite priority, FeatureSeeder runs first due to dependency
```

### Example 4: Mixed Priority and Dependencies

```php
// Seeders at the same dependency level sort by priority

#[SeederPriority(10)]
class FeatureSeeder extends Seeder { }

#[SeederAfter(FeatureSeeder::class)]
#[SeederPriority(20)]  // Lower priority = runs first among siblings
class PackageSeeder extends Seeder { }

#[SeederAfter(FeatureSeeder::class)]
#[SeederPriority(30)]  // Higher priority = runs after PackageSeeder
class PlanSeeder extends Seeder { }

// Order: Feature -> Package -> Plan
// (Package before Plan because 20 < 30)
```

## Circular Dependency Errors

Circular dependencies are detected and throw `CircularDependencyException`.

### What Causes Circular Dependencies

```php
// This creates a cycle: A -> B -> C -> A

#[SeederAfter(SeederC::class)]
class SeederA extends Seeder { }

#[SeederAfter(SeederA::class)]
class SeederB extends Seeder { }

#[SeederAfter(SeederB::class)]
class SeederC extends Seeder { }
```

### Error Handling

```php
use Core\Database\Seeders\Exceptions\CircularDependencyException;

try {
    $seeders = $discovery->discover();
} catch (CircularDependencyException $e) {
    echo $e->getMessage();
    // "Circular dependency detected in seeders: SeederA -> SeederB -> SeederC -> SeederA"

    // Get the cycle chain
    $cycle = $e->cycle;
    // ['SeederA', 'SeederB', 'SeederC', 'SeederA']
}
```

### Debugging Circular Dependencies

1. Check the exception message for the cycle path
2. Review the `$after` and `$before` declarations
3. Remember that `#[SeederBefore]` creates implicit `after` relationships
4. Use the registry to inspect relationships:

```php
$discovery = new SeederDiscovery([app_path('Mod')]);
$seeders = $discovery->getSeeders();

foreach ($seeders as $class => $meta) {
    echo "{$class}:\n";
    echo "  Priority: {$meta['priority']}\n";
    echo "  After: " . implode(', ', $meta['after']) . "\n";
    echo "  Before: " . implode(', ', $meta['before']) . "\n";
}
```

## Manual Registration

Use `SeederRegistry` for explicit control over seeder ordering:

```php
use Core\Database\Seeders\SeederRegistry;

$registry = new SeederRegistry();

// Register with options
$registry
    ->register(FeatureSeeder::class, priority: 10)
    ->register(PackageSeeder::class, after: [FeatureSeeder::class])
    ->register(WorkspaceSeeder::class, after: [PackageSeeder::class]);

// Get ordered list
$seeders = $registry->getOrdered();
```

### Bulk Registration

```php
$registry->registerMany([
    FeatureSeeder::class => 10,  // Priority shorthand
    PackageSeeder::class => [
        'priority' => 50,
        'after' => [FeatureSeeder::class],
    ],
    WorkspaceSeeder::class => [
        'priority' => 50,
        'after' => [PackageSeeder::class],
        'before' => [DemoSeeder::class],
    ],
]);
```

### Registry Operations

```php
// Check if registered
$registry->has(FeatureSeeder::class);

// Remove a seeder
$registry->remove(DemoSeeder::class);

// Merge registries
$registry->merge($otherRegistry);

// Clear all
$registry->clear();
```

## CoreDatabaseSeeder

Extend `CoreDatabaseSeeder` for automatic discovery in your application:

### Basic Usage

```php
<?php

namespace Database\Seeders;

use Core\Database\Seeders\CoreDatabaseSeeder;

class DatabaseSeeder extends CoreDatabaseSeeder
{
    // Uses auto-discovery by default
}
```

### Custom Paths

```php
class DatabaseSeeder extends CoreDatabaseSeeder
{
    protected function getSeederPaths(): array
    {
        return [
            app_path('Core'),
            app_path('Mod'),
            base_path('packages/my-package/src'),
        ];
    }
}
```

### Excluding Seeders

```php
class DatabaseSeeder extends CoreDatabaseSeeder
{
    protected function getExcludedSeeders(): array
    {
        return [
            DemoDataSeeder::class,
            TestUserSeeder::class,
        ];
    }
}
```

### Disabling Auto-Discovery

```php
class DatabaseSeeder extends CoreDatabaseSeeder
{
    protected bool $autoDiscover = false;

    protected function registerSeeders(SeederRegistry $registry): void
    {
        $registry
            ->register(FeatureSeeder::class, priority: 10)
            ->register(PackageSeeder::class, priority: 20)
            ->register(UserSeeder::class, priority: 30);
    }
}
```

## Command-Line Filtering

Filter seeders when running `db:seed`:

```bash
# Exclude specific seeders
php artisan db:seed --exclude=DemoSeeder

# Exclude multiple
php artisan db:seed --exclude=DemoSeeder --exclude=TestSeeder

# Run only specific seeders
php artisan db:seed --only=UserSeeder

# Run multiple specific seeders
php artisan db:seed --only=UserSeeder --only=FeatureSeeder
```

### Pattern Matching

Filters support multiple matching strategies:

```bash
# Full class name
php artisan db:seed --exclude=Mod\\Blog\\Database\\Seeders\\PostSeeder

# Short name
php artisan db:seed --exclude=PostSeeder

# Partial match
php artisan db:seed --exclude=Demo  # Matches DemoSeeder, DemoDataSeeder, etc.
```

## Configuration

Configure the seeder system in `config/core.php`:

```php
return [
    'seeders' => [
        // Enable auto-discovery
        'auto_discover' => env('CORE_SEEDER_AUTODISCOVER', true),

        // Paths to scan
        'paths' => [
            app_path('Core'),
            app_path('Mod'),
            app_path('Website'),
        ],

        // Classes to exclude
        'exclude' => [
            // App\Mod\Demo\Database\Seeders\DemoSeeder::class,
        ],
    ],
];
```

## Best Practices

### 1. Use Explicit Dependencies

```php
// Preferred: Explicit dependencies
#[SeederAfter(FeatureSeeder::class)]
class PackageSeeder extends Seeder { }

// Avoid: Relying only on priority for ordering
#[SeederPriority(51)]  // Fragile - assumes FeatureSeeder is 50
class PackageSeeder extends Seeder { }
```

### 2. Keep Seeders Focused

```php
// Good: Single responsibility
class PostSeeder extends Seeder {
    public function run(): void {
        Post::factory()->count(50)->create();
    }
}

// Avoid: Monolithic seeders
class EverythingSeeder extends Seeder {
    public function run(): void {
        // Creates users, posts, comments, categories, tags...
    }
}
```

### 3. Use Factories in Seeders

```php
class PostSeeder extends Seeder
{
    public function run(): void
    {
        // Good: Use factories for consistent test data
        Post::factory()
            ->count(50)
            ->has(Comment::factory()->count(3))
            ->create();
    }
}
```

### 4. Handle Idempotency

```php
class FeatureSeeder extends Seeder
{
    public function run(): void
    {
        // Good: Use updateOrCreate for idempotent seeding
        Feature::updateOrCreate(
            ['code' => 'blog'],
            ['name' => 'Blog', 'enabled' => true]
        );
    }
}
```

### 5. Document Dependencies

```php
/**
 * Seeds packages for the tenant module.
 *
 * Requires:
 * - FeatureSeeder: Features must exist to link packages
 * - TenantSeeder: Tenants must exist to assign packages
 */
#[SeederAfter(FeatureSeeder::class, TenantSeeder::class)]
class PackageSeeder extends Seeder { }
```

## Troubleshooting

### Seeders Not Discovered

1. Check the file is in `Database/Seeders/` subdirectory
2. Verify class name ends with `Seeder`
3. Confirm namespace matches file location
4. Check the path is included in discovery paths

### Wrong Execution Order

1. Print discovery results to verify:
   ```php
   $discovery = new SeederDiscovery([app_path('Mod')]);
   dd($discovery->getSeeders());
   ```
2. Check for missing `#[SeederAfter]` declarations
3. Verify priority values (lower runs first)

### Circular Dependency Error

1. Read the error message for the cycle
2. Draw out the dependency graph
3. Identify which relationship should be removed/reversed
4. Consider if the circular dependency indicates a design issue

## Learn More

- [Module System](/core/modules)
- [Service Contracts](/core/service-contracts)
- [Configuration](/core/configuration)
