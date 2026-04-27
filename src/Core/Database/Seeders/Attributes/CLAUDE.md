# Database/Seeders/Attributes/ — Seeder Ordering Attributes

PHP 8 attributes for controlling seeder execution order in the auto-discovery system.

## Attributes

| Attribute | Target | Purpose |
|-----------|--------|---------|
| `#[SeederAfter(...)]` | Class | This seeder must run after the specified seeders. Repeatable. |
| `#[SeederBefore(...)]` | Class | This seeder must run before the specified seeders. Repeatable. |
| `#[SeederPriority(n)]` | Class | Numeric priority (lower runs first, default 50). |

## Priority Guidelines

- 0-20: Foundation (features, configuration)
- 20-40: Core data (packages, workspaces)
- 40-60: Default (general seeders)
- 60-80: Content (pages, posts)
- 80-100: Demo/test data

## Example

```php
#[SeederAfter(FeatureSeeder::class)]
#[SeederPriority(30)]
class PackageSeeder extends Seeder { ... }
```
