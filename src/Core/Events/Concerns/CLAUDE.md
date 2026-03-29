# Events/Concerns/ — Event Version Compatibility

## Traits

| Trait | Purpose |
|-------|---------|
| `HasEventVersion` | For Boot classes to declare which event API versions they support. Methods: `getRequiredEventVersion(eventClass)`, `isCompatibleWithEventVersion(eventClass, version)`, `getEventVersionRequirements()`. |

Enables graceful handling of event API changes. Modules declare minimum versions via `$eventVersions` static property. The framework checks compatibility during bootstrap and logs warnings for version mismatches.
