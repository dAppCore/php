# Media/Jobs/ — Media Background Jobs

## Jobs

| Job | Purpose |
|-----|---------|
| `ProcessMediaConversion` | Queued job for running media conversions asynchronously. Dispatches `ConversionProgress` events during processing. Logs failures. |
| `GenerateThumbnail` | Queued job for generating thumbnails on demand. Called by `LazyThumbnail` when a thumbnail doesn't exist yet. |
