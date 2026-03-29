# Cdn/Facades/ — CDN Facade

## Facades

| Facade | Resolves To | Purpose |
|--------|-------------|---------|
| `Cdn` | `StorageUrlResolver` | Static proxy for CDN operations — `cdn()`, `origin()`, `private()`, `signedUrl()`, `asset()`, `pushToCdn()`, `deleteFromCdn()`, `purge()`, `storePublic()`, `storePrivate()`, `vBucketCdn()`, and more. |

Usage: `Cdn::cdn('images/logo.png')` returns the CDN URL for the asset.
