# Config/Enums/ — Config Type System

Backed enums for the configuration system's type safety and scope hierarchy.

## Enums

| Enum | Values | Purpose |
|------|--------|---------|
| `ConfigType` | STRING, BOOL, INT, FLOAT, ARRAY, JSON | Determines how config values are cast and validated. Has `cast()` and `default()` methods. |
| `ScopeType` | SYSTEM, ORG, WORKSPACE | Defines the inheritance hierarchy. Resolution order: workspace (priority 20) > org (10) > system (0). |

`ScopeType::resolutionOrder()` returns scopes from most specific to least specific for cascade resolution.
