# Database/Seeders/Exceptions/ — Seeder Exception Types

## Files

| File | Purpose |
|------|---------|
| `CircularDependencyException.php` | Thrown when seeder dependency graph contains a cycle. Includes the `$cycle` array showing the loop path. Has `fromPath()` factory for building from a traversal path. |

Part of the seeder auto-discovery system. The `CoreDatabaseSeeder` uses topological sorting on `#[SeederAfter]`/`#[SeederBefore]` attributes and throws this when a cycle is detected.
