# Seeder Discovery & Ordering

Core PHP Framework provides automatic seeder discovery with dependency-based ordering. Define seeder dependencies using PHP attributes and let the framework handle execution order.

## Overview

Traditional Laravel seeders require manual ordering in `DatabaseSeeder`. Core PHP automatically discovers seeders across modules and orders them based on declared dependencies.

### Traditional Approach

```php
// database/seeders/DatabaseSeeder.php
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Manual ordering - easy to get wrong
        $this->call([
            WorkspaceSeeder::class,
            UserSeeder::class,
            CategorySeeder::class,
            PostSeeder::class,
            CommentSeeder::class,
        ]);
    }
}
```

**Problems:**
- Manual dependency management
- Order mistakes cause failures
- Scattered across modules but centrally managed
- Hard to maintain as modules grow

### Discovery Approach

```php
// Mod/Tenant/Database/Seeders/WorkspaceSeeder.php
#[SeederPriority(100)]
class WorkspaceSeeder extends Seeder
{
    public function run(): void { /* ... */ }
}

// Mod/Blog/Database/Seeders/CategorySeeder.php
#[SeederPriority(50)]
#[SeederAfter(WorkspaceSeeder::class)]
class CategorySeeder extends Seeder
{
    public function run(): void { /* ... */ }
}

// Mod/Blog/Database/Seeders/PostSeeder.php
#[SeederAfter(CategorySeeder::class)]
class PostSeeder extends Seeder
{
    public function run(): void { /* ... */ }
}
```

**Benefits:**
- Automatic discovery across modules
- Explicit dependency declarations
- Topological sorting handles execution order
- Circular dependency detection
- Each module manages its own seeders

## Configuration

### Enable Auto-Discovery

```php
// config/core.php
'seeders' => [
    'auto_discover' => env('SEEDERS_AUTO_DISCOVER', true),
    'paths' => [
        'Mod/*/Database/Seeders',
        'Core/*/Database/Seeders',
        'Plug/*/Database/Seeders',
    ],
    'exclude' => [
        'DatabaseSeeder',
        'CoreDatabaseSeeder',
    ],
],
```

### Create Core Seeder

Create a root seeder that uses discovery:

```php
<?php
// database/seeders/DatabaseSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Core\Database\Seeders\SeederRegistry;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $registry = app(SeederRegistry::class);

        // Automatically discover and order seeders
        $seeders = $registry->getOrderedSeeders();

        $this->call($seeders);
    }
}
```

## Seeder Attributes

### SeederPriority

Set execution priority (higher = runs earlier):

```php
<?php

namespace Mod\Tenant\Database\Seeders;

use Illuminate\Database\Seeder;
use Core\Database\Seeders\Attributes\SeederPriority;

#[SeederPriority(100)]
class WorkspaceSeeder extends Seeder
{
    public function run(): void
    {
        Workspace::factory()->count(3)->create();
    }
}
```

**Priority Ranges:**
- `100+` - Foundation data (workspaces, system records)
- `50-99` - Core domain data (users, categories)
- `1-49` - Feature data (posts, comments)
- `0` - Default priority
- `<0` - Post-processing (analytics, cache warming)

### SeederAfter

Run after specific seeders:

```php
<?php

namespace Mod\Blog\Database\Seeders;

use Illuminate\Database\Seeder;
use Core\Database\Seeders\Attributes\SeederAfter;
use Mod\Tenant\Database\Seeders\WorkspaceSeeder;

#[SeederAfter(WorkspaceSeeder::class)]
class CategorySeeder extends Seeder
{
    public function run(): void
    {
        Category::factory()->count(5)->create();
    }
}
```

### SeederBefore

Run before specific seeders:

```php
<?php

namespace Mod\Blog\Database\Seeders;

use Illuminate\Database\Seeder;
use Core\Database\Seeders\Attributes\SeederBefore;

#[SeederBefore(PostSeeder::class)]
class CategorySeeder extends Seeder
{
    public function run(): void
    {
        Category::factory()->count(5)->create();
    }
}
```

### Combining Attributes

Use multiple attributes for complex dependencies:

```php
#[SeederPriority(50)]
#[SeederAfter(WorkspaceSeeder::class, UserSeeder::class)]
#[SeederBefore(CommentSeeder::class)]
class PostSeeder extends Seeder
{
    public function run(): void
    {
        Post::factory()->count(20)->create();
    }
}
```

## Execution Order

### Topological Sorting

The framework automatically orders seeders using topological sorting:

```
Given seeders:
  - WorkspaceSeeder (priority: 100)
  - UserSeeder (priority: 90, after: WorkspaceSeeder)
  - CategorySeeder (priority: 50, after: WorkspaceSeeder)
  - PostSeeder (priority: 40, after: CategorySeeder, UserSeeder)
  - CommentSeeder (priority: 30, after: PostSeeder, UserSeeder)

Execution order:
  1. WorkspaceSeeder (priority 100)
  2. UserSeeder (priority 90, depends on Workspace)
  3. CategorySeeder (priority 50, depends on Workspace)
  4. PostSeeder (priority 40, depends on Category & User)
  5. CommentSeeder (priority 30, depends on Post & User)
```

### Resolution Algorithm

1. Group seeders by priority (high to low)
2. Within each priority group, perform topological sort
3. Detect circular dependencies
4. Execute in resolved order

## Circular Dependency Detection

The framework detects and prevents circular dependencies:

```php
// ❌ This will throw CircularDependencyException

#[SeederAfter(SeederB::class)]
class SeederA extends Seeder { }

#[SeederAfter(SeederC::class)]
class SeederB extends Seeder { }

#[SeederAfter(SeederA::class)]
class SeederC extends Seeder { }

// Error: Circular dependency detected: SeederA → SeederB → SeederC → SeederA
```

## Module Seeders

### Typical Module Structure

```
Mod/Blog/Database/Seeders/
├── BlogSeeder.php           # Optional: calls other seeders
├── CategorySeeder.php       # Creates categories
├── PostSeeder.php           # Creates posts
└── DemoContentSeeder.php    # Creates demo data
```

### Module Seeder Example

```php
<?php

namespace Mod\Blog\Database\Seeders;

use Illuminate\Database\Seeder;
use Core\Database\Seeders\Attributes\SeederPriority;
use Core\Database\Seeders\Attributes\SeederAfter;
use Mod\Tenant\Database\Seeders\WorkspaceSeeder;

#[SeederPriority(50)]
#[SeederAfter(WorkspaceSeeder::class)]
class BlogSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
            PostSeeder::class,
        ]);
    }
}
```

### Environment-Specific Seeding

```php
#[SeederPriority(10)]
class DemoContentSeeder extends Seeder
{
    public function run(): void
    {
        // Only seed demo data in non-production
        if (app()->environment('production')) {
            return;
        }

        Post::factory()
            ->count(50)
            ->published()
            ->create();
    }
}
```

## Conditional Seeding

### Feature Flags

```php
class AnalyticsSeeder extends Seeder
{
    public function run(): void
    {
        if (! Feature::active('analytics')) {
            $this->command->info('Skipping analytics seeder (feature disabled)');
            return;
        }

        // Seed analytics data
    }
}
```

### Configuration

```php
class EmailSeeder extends Seeder
{
    public function run(): void
    {
        if (! config('modules.email.enabled')) {
            return;
        }

        EmailTemplate::factory()->count(10)->create();
    }
}
```

### Database Check

```php
class MigrationSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('legacy_posts')) {
            return;
        }

        // Migrate legacy data
    }
}
```

## Factory Integration

Seeders commonly use factories:

```php
<?php

namespace Mod\Blog\Database\Seeders;

use Illuminate\Database\Seeder;
use Mod\Blog\Models\Post;
use Mod\Blog\Models\Category;

class PostSeeder extends Seeder
{
    public function run(): void
    {
        // Create categories first
        $categories = Category::factory()->count(5)->create();

        // Create posts for each category
        $categories->each(function ($category) {
            Post::factory()
                ->count(10)
                ->for($category)
                ->published()
                ->create();
        });

        // Create unpublished drafts
        Post::factory()
            ->count(5)
            ->draft()
            ->create();
    }
}
```

## Testing Seeders

### Unit Testing

```php
<?php

namespace Tests\Unit\Database\Seeders;

use Tests\TestCase;
use Mod\Blog\Database\Seeders\PostSeeder;
use Mod\Blog\Models\Post;

class PostSeederTest extends TestCase
{
    public function test_creates_posts(): void
    {
        $this->seed(PostSeeder::class);

        $this->assertDatabaseCount('posts', 20);
    }

    public function test_posts_have_categories(): void
    {
        $this->seed(PostSeeder::class);

        $posts = Post::all();

        $posts->each(function ($post) {
            $this->assertNotNull($post->category_id);
        });
    }
}
```

### Integration Testing

```php
public function test_seeder_execution_order(): void
{
    $registry = app(SeederRegistry::class);

    $seeders = $registry->getOrderedSeeders();

    $workspaceIndex = array_search(WorkspaceSeeder::class, $seeders);
    $userIndex = array_search(UserSeeder::class, $seeders);
    $postIndex = array_search(PostSeeder::class, $seeders);

    $this->assertLessThan($userIndex, $workspaceIndex);
    $this->assertLessThan($postIndex, $userIndex);
}
```

### Circular Dependency Testing

```php
public function test_detects_circular_dependencies(): void
{
    $this->expectException(CircularDependencyException::class);

    // Force circular dependency
    $registry = app(SeederRegistry::class);
    $registry->register([
        CircularA::class,
        CircularB::class,
        CircularC::class,
    ]);

    $registry->getOrderedSeeders();
}
```

## Performance

### Chunking

Seed large datasets in chunks:

```php
public function run(): void
{
    $faker = Faker\Factory::create();

    // Seed in chunks for better memory usage
    for ($i = 0; $i < 10; $i++) {
        Post::factory()
            ->count(100)
            ->create();

        $this->command->info("Seeded batch " . ($i + 1) . "/10");
    }
}
```

### Database Transactions

Wrap seeders in transactions for performance:

```php
public function run(): void
{
    DB::transaction(function () {
        Post::factory()->count(1000)->create();
    });
}
```

### Disable Event Listeners

Skip event listeners during seeding:

```php
public function run(): void
{
    // Disable events for performance
    Post::withoutEvents(function () {
        Post::factory()->count(1000)->create();
    });
}
```

## Debugging

### Verbose Output

```bash
# Show seeder execution details
php artisan db:seed --verbose

# Show discovered seeders
php artisan db:seed --show-seeders
```

### Dry Run

```bash
# Preview seeder order without executing
php artisan db:seed --dry-run
```

### Seeder Registry Inspection

```php
$registry = app(SeederRegistry::class);

// Get all discovered seeders
$seeders = $registry->getAllSeeders();

// Get execution order
$ordered = $registry->getOrderedSeeders();

// Get seeder metadata
$metadata = $registry->getMetadata(PostSeeder::class);
```

## Best Practices

### 1. Use Priorities for Groups

```php
// ✅ Good - clear priority groups
#[SeederPriority(100)] // Foundation
class WorkspaceSeeder { }

#[SeederPriority(50)] // Core domain
class CategorySeeder { }

#[SeederPriority(10)] // Feature data
class PostSeeder { }
```

### 2. Explicit Dependencies

```php
// ✅ Good - explicit dependencies
#[SeederAfter(WorkspaceSeeder::class, CategorySeeder::class)]
class PostSeeder { }

// ❌ Bad - implicit dependencies via priority alone
#[SeederPriority(40)]
class PostSeeder { }
```

### 3. Idempotent Seeders

```php
// ✅ Good - safe to run multiple times
public function run(): void
{
    if (Category::exists()) {
        return;
    }

    Category::factory()->count(5)->create();
}

// ❌ Bad - creates duplicates
public function run(): void
{
    Category::factory()->count(5)->create();
}
```

### 4. Environment Awareness

```php
// ✅ Good - respects environment
public function run(): void
{
    $count = app()->environment('production') ? 10 : 100;

    Post::factory()->count($count)->create();
}
```

### 5. Meaningful Names

```php
// ✅ Good names
class WorkspaceSeeder { }
class BlogDemoContentSeeder { }
class LegacyPostMigrationSeeder { }

// ❌ Bad names
class Seeder1 { }
class TestSeeder { }
class DataSeeder { }
```

## Running Seeders

```bash
# Run all seeders
php artisan db:seed

# Run specific seeder
php artisan db:seed --class=PostSeeder

# Fresh database with seeding
php artisan migrate:fresh --seed

# Seed specific modules
php artisan db:seed --module=Blog

# Seed with environment
php artisan db:seed --env=staging
```

## Learn More

- [Database Factories](/patterns-guide/factories)
- [Module System](/architecture/module-system)
- [Testing Seeders](/testing/seeders)
