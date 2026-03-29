# Cdn

BunnyCDN integration with vBucket workspace isolation and storage offloading.

## What It Does

Unified CDN and object storage layer providing:
- BunnyCDN pull zone operations (purge, stats)
- BunnyCDN storage zone operations (upload, download, list, delete)
- Context-aware URL building (CDN, origin, private, signed)
- vBucket-scoped paths using `LthnHash` for tenant isolation
- Asset pipeline for processing and offloading
- Flux Pro CDN delivery
- Storage offload migration from local to CDN

## Key Classes

| Class | Purpose |
|-------|---------|
| `Boot` | ServiceProvider registering all services as singletons + backward-compat aliases to `App\` namespaces |
| `BunnyCdnService` | Pull zone API: `purgeUrl()`, `purgeUrls()`, `purgeAll()`, `purgeByTag()`, `purgeWorkspace()`, `getStats()`, `getBandwidth()`, `listStorageFiles()`, `uploadFile()`, `deleteFile()`. Sanitises error messages to redact API keys |
| `BunnyStorageService` | Direct storage zone operations (separate from pull zone API) |
| `CdnUrlBuilder` | URL construction: `cdn()`, `origin()`, `private()`, `apex()`, `signed()`, `vBucket()`, `vBucketId()`, `vBucketPath()`, `asset()`, `withVersion()`, `urls()`, `allUrls()` |
| `StorageUrlResolver` | Context-aware URL resolution |
| `FluxCdnService` | Flux Pro component CDN delivery |
| `AssetPipeline` | Asset processing pipeline |
| `StorageOffload` (service) | Migrates files from local storage to CDN |
| `StorageOffload` (model) | Tracks offloaded files in DB |
| `Cdn` (facade) | `Cdn::purge(...)` etc. |
| `HasCdnUrls` (trait) | Adds CDN URL methods to Eloquent models |

## Console Commands

- `cdn:purge` -- Purge CDN cache
- `cdn:push-assets` -- Push assets to CDN storage
- `cdn:push-flux` -- Push Flux Pro assets to CDN
- `cdn:offload-migrate` -- Migrate local files to CDN storage

## Middleware

- `RewriteOffloadedUrls` -- Rewrites storage URLs in responses to CDN URLs
- `LocalCdnMiddleware` -- Serves CDN assets locally in development

## vBucket Pattern

Workspace-isolated CDN paths using `LthnHash::vBucketId()`:
```
cdn.example.com/{vBucketId}/path/to/asset.js
```
The vBucketId is a deterministic SHA-256 hash of the domain name, ensuring each workspace's assets are namespaced.

## Integration

- Reads credentials from `ConfigService` (DB-backed config), not just `.env`
- Signed URLs use HMAC-SHA256 with BunnyCDN token authentication
- Config files: `config.php` (CDN settings), `offload.php` (storage offload settings)
- Backward-compat aliases registered for all `App\Services\*` and `App\Models\*` namespaces
