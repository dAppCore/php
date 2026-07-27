# Database/Seeders

Auto-discovering, dependency-aware seeder orchestration.

## What It Does

Replaces Laravel's manual seeder ordering with automatic discovery and topological sorting. Seeders declare their dependencies via attributes or properties, and the framework figures out the correct execution order using Kahn's algorithm.

## Key Classes

| Class | Purpose |
|-------|---------|
| `CoreDatabaseSeeder` | Base seeder class. Extends Laravel's `Seeder`. Auto-discovers seeders from configured paths, applies `--exclude` and `--only` filters, runs them in dependency order |
| `SeederDiscovery` | Scans directories for `*Seeder.php` files, reads priority/dependency metadata from attributes or properties, produces topologically sorted list |
| `SeederRegistry` | Manual registration alternative: `register(Class, priority: 10, after: [...])`. Fluent API with `registerMany()`, `merge()`, `getOrdered()` |

## Attributes

| Attribute | Target | Purpose |
|-----------|--------|---------|
| `#[SeederPriority(10)]` | Class | Lower values run first (default: 50) |
| `#[SeederAfter(FeatureSeeder::class)]` | Class | Must run after specified seeders (repeatable) |
| `#[SeederBefore(PackageSeeder::class)]` | Class | Must run before specified seeders (repeatable) |

## Priority Guidelines

- 0-20: Foundation (features, configuration)
- 20-40: Core data (packages, workspaces)
- 40-60: Default (general seeders)
- 60-80: Content (pages, posts)
- 80-100: Demo/test data

## Ordering Rules

Dependencies take precedence over priority. Within the same dependency level, lower priority numbers run first. Circular dependencies throw `CircularDependencyException` with the full cycle path.

## Usage

```php
// Auto-discovery (default)
class DatabaseSeeder extends CoreDatabaseSeeder {
    protected function getSeederPaths(): array {
        return [app_path('Core'), app_path('Mod')];
    }
}

// Manual registration
class DatabaseSeeder extends CoreDatabaseSeeder {
    protected bool $autoDiscover = false;
    protected function registerSeeders(SeederRegistry $registry): void {
        $registry->register(FeatureSeeder::class, priority: 10)
                 ->register(PackageSeeder::class, after: [FeatureSeeder::class]);
    }
}

// CLI filtering
php artisan db:seed --exclude=DemoSeeder --only=FeatureSeeder
```

## Discovery Paths

Scans `{path}/*/Database/Seeders/*Seeder.php` (module subdirs) and `{path}/Database/Seeders/*Seeder.php` (direct). Configured via `core.seeders.paths` or defaults to `app/Core`, `app/Mod`, `app/Website`.

## Integration

- Properties alternative to attributes: `public int $priority = 10;`, `public array $after = [...]`, `public array $before = [...]`
- Pattern matching for `--exclude`/`--only`: full class name, short name, or partial match
- Config: `core.seeders.auto_discover`, `core.seeders.paths`, `core.seeders.exclude`
