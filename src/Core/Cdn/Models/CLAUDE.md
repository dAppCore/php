# Cdn/Models/ — CDN Storage Models

## Models

| Model | Purpose |
|-------|---------|
| `StorageOffload` | Tracks files offloaded to remote storage. Records local path, remote path, disk, SHA-256 hash, file size, MIME type, category, metadata, and offload timestamp. Used by the URL rewriting middleware. |
