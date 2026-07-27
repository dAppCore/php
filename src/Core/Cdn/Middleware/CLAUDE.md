# Cdn/Middleware/ — CDN HTTP Middleware

## Middleware

| Class | Purpose |
|-------|---------|
| `LocalCdnMiddleware` | Adds aggressive caching headers and compression for requests on the `cdn.*` subdomain. Provides CDN-like behaviour without external services. |
| `RewriteOffloadedUrls` | Processes JSON responses and replaces local storage paths with remote equivalents when files have been offloaded to external storage. |
