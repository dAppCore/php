# Cdn/Traits/ — CDN Model Traits

## Traits

| Trait | Purpose |
|-------|---------|
| `HasCdnUrls` | For models with asset paths needing CDN URL resolution. Requires `$cdnPathAttribute` (attribute with storage path) and optional `$cdnBucket` (`public` or `private`). Provides `cdnUrl()` accessor. |
