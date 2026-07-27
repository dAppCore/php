# Config/Tests/Feature/ — Config Integration Tests

Pest feature tests for the hierarchical configuration system.

## Test Files

| File | Purpose |
|------|---------|
| `ConfigServiceTest.php` | Full integration tests covering ConfigKey creation, ConfigProfile inheritance, ConfigResolver scope cascading, FINAL lock enforcement, ConfigService materialised reads/writes, ConfigResolved storage, and the single-hash lazy-load pattern. |

Tests cover the complete config lifecycle: key definition, profile hierarchy (system/workspace), value resolution with inheritance, lock semantics, cache invalidation, and the prime/materialise flow.
