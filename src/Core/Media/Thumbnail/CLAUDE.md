# Media/Thumbnail/ — Lazy Thumbnail Generation

## Files

| File | Purpose |
|------|---------|
| `LazyThumbnail` | On-demand thumbnail generation service. Generates thumbnails when first requested rather than eagerly on upload. Caches generated thumbnails. Dispatches `GenerateThumbnail` job for async processing. |
| `ThumbnailController` | HTTP controller serving thumbnail requests. Generates on-the-fly if not cached. |
| `helpers.php` | Helper functions for thumbnail URL generation in Blade templates. |

Thumbnails are generated lazily to avoid processing overhead on upload and to only create sizes that are actually requested.
