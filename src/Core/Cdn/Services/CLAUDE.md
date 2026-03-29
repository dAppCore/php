# Cdn/Services/ — CDN Service Layer

## Services

| Service | Purpose |
|---------|---------|
| `BunnyCdnService` | BunnyCDN pull zone API — cache purging (URL, tag, workspace, global), statistics retrieval, pull zone management. Uses config from `ConfigService`. |
| `BunnyStorageService` | BunnyCDN storage zone API — file upload, download, delete, list. Supports public and private storage zones. |
| `StorageOffload` (service) | Manages file offloading to remote storage — upload, track, verify. Creates `StorageOffload` model records. |
| `StorageUrlResolver` | URL builder for all asset contexts — CDN, origin, private, signed, apex. Supports virtual buckets (vBucket) per domain. Backs the `Cdn` facade. |
| `CdnUrlBuilder` | Low-level URL construction for CDN paths with cache-busting and domain resolution. |
| `AssetPipeline` | Orchestrates asset processing — push to CDN, cache headers, versioning. |
| `FluxCdnService` | Pushes Flux UI assets to CDN for faster component loading. |
