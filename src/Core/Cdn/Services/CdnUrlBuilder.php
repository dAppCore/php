<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Cdn\Services;

use Carbon\Carbon;
use Core\Crypt\LthnHash;

/**
 * Centralized URL building for CDN operations.
 *
 * Extracts URL building logic from StorageUrlResolver and other CDN services
 * into a dedicated class for consistency and reusability.
 *
 * ## URL Types
 *
 * | Type | Description | Example |
 * |------|-------------|---------|
 * | CDN | Pull zone delivery URL | https://cdn.example.com/path |
 * | Origin | Origin storage URL (Hetzner) | https://storage.example.com/path |
 * | Private | Private bucket URL (gated) | https://private.example.com/path |
 * | Apex | Main domain fallback | https://example.com/path |
 * | Signed | Token-authenticated URL | https://cdn.example.com/path?token=xxx |
 *
 * ## vBucket Scoping
 *
 * Uses LTHN QuasiHash for workspace-isolated CDN paths:
 * ```
 * cdn.example.com/{vBucketId}/path/to/asset.js
 * ```
 *
 * ## Methods
 *
 * | Method | Returns | Description |
 * |--------|---------|-------------|
 * | `cdn()` | `string` | Build CDN delivery URL |
 * | `origin()` | `string` | Build origin storage URL |
 * | `private()` | `string` | Build private bucket URL |
 * | `apex()` | `string` | Build apex domain URL |
 * | `signed()` | `string\|null` | Build signed URL for private content |
 * | `vBucket()` | `string` | Build vBucket-scoped URL |
 * | `vBucketId()` | `string` | Generate vBucket ID for a domain |
 * | `vBucketPath()` | `string` | Build vBucket-scoped storage path |
 * | `asset()` | `string` | Build context-aware asset URL |
 * | `withVersion()` | `string` | Build URL with version query param |
 */
class CdnUrlBuilder
{
    /**
     * Build a CDN delivery URL for a path.
     *
     * @param  string  $path  Path relative to CDN root
     * @param  string|null  $baseUrl  Optional base URL override (uses config if null)
     * @return string Full CDN URL
     */
    public function cdn(string $path, ?string $baseUrl = null): string
    {
        $baseUrl = $baseUrl ?? config('cdn.urls.cdn');

        return $this->build($baseUrl, $path);
    }

    /**
     * Build an origin storage URL for a path.
     *
     * @param  string  $path  Path relative to storage root
     * @param  string|null  $baseUrl  Optional base URL override (uses config if null)
     * @return string Full origin URL
     */
    public function origin(string $path, ?string $baseUrl = null): string
    {
        $baseUrl = $baseUrl ?? config('cdn.urls.public');

        return $this->build($baseUrl, $path);
    }

    /**
     * Build a private storage URL for a path.
     *
     * @param  string  $path  Path relative to storage root
     * @param  string|null  $baseUrl  Optional base URL override (uses config if null)
     * @return string Full private URL
     */
    public function private(string $path, ?string $baseUrl = null): string
    {
        $baseUrl = $baseUrl ?? config('cdn.urls.private');

        return $this->build($baseUrl, $path);
    }

    /**
     * Build an apex domain URL for a path.
     *
     * @param  string  $path  Path relative to web root
     * @param  string|null  $baseUrl  Optional base URL override (uses config if null)
     * @return string Full apex URL
     */
    public function apex(string $path, ?string $baseUrl = null): string
    {
        $baseUrl = $baseUrl ?? config('cdn.urls.apex');

        return $this->build($baseUrl, $path);
    }

    /**
     * Build a signed URL for private CDN content with token authentication.
     *
     * @param  string  $path  Path relative to storage root
     * @param  int|Carbon|null  $expiry  Expiry time in seconds, or Carbon instance.
     *                                   Defaults to config('cdn.signed_url_expiry', 3600)
     * @param  string|null  $token  Optional token override (uses config if null)
     * @return string|null Signed URL or null if token not configured
     */
    public function signed(string $path, int|Carbon|null $expiry = null, ?string $token = null): ?string
    {
        $token = $token ?? config('cdn.bunny.private.token');

        if (empty($token)) {
            return null;
        }

        // Resolve expiry to Unix timestamp
        $expires = $this->resolveExpiry($expiry);
        $path = '/'.ltrim($path, '/');

        // BunnyCDN token authentication format (using HMAC for security)
        $hashableBase = $token.$path.$expires;
        $hash = base64_encode(hash_hmac('sha256', $hashableBase, $token, true));

        // URL-safe base64
        $hash = str_replace(['+', '/'], ['-', '_'], $hash);
        $hash = rtrim($hash, '=');

        // Build base URL from config
        $baseUrl = $this->buildSignedUrlBase();

        return "{$baseUrl}{$path}?token={$hash}&expires={$expires}";
    }

    /**
     * Build a vBucket-scoped CDN URL.
     *
     * @param  string  $domain  The workspace domain for scoping
     * @param  string  $path  Path relative to vBucket root
     * @param  string|null  $baseUrl  Optional base URL override
     * @return string Full vBucket-scoped CDN URL
     */
    public function vBucket(string $domain, string $path, ?string $baseUrl = null): string
    {
        $vBucketId = $this->vBucketId($domain);
        $scopedPath = $this->vBucketPath($domain, $path);

        return $this->cdn($scopedPath, $baseUrl);
    }

    /**
     * Generate a vBucket ID for a domain/workspace.
     *
     * Uses LTHN QuasiHash for deterministic, scoped identifiers.
     *
     * @param  string  $domain  The domain name (e.g., "example.com")
     * @return string 16-character vBucket identifier
     */
    public function vBucketId(string $domain): string
    {
        return LthnHash::vBucketId($domain);
    }

    /**
     * Build a vBucket-scoped storage path.
     *
     * @param  string  $domain  The workspace domain for scoping
     * @param  string  $path  Path relative to vBucket root
     * @return string Full storage path with vBucket prefix
     */
    public function vBucketPath(string $domain, string $path): string
    {
        $vBucketId = $this->vBucketId($domain);

        return "{$vBucketId}/".ltrim($path, '/');
    }

    /**
     * Build a context-aware asset URL.
     *
     * @param  string  $path  Path relative to storage root
     * @param  string  $context  Context ('admin', 'public')
     * @return string URL appropriate for the context
     */
    public function asset(string $path, string $context = 'public'): string
    {
        return $context === 'admin' ? $this->origin($path) : $this->cdn($path);
    }

    /**
     * Build a URL with version query parameter for cache busting.
     *
     * @param  string  $url  The base URL
     * @param  string|null  $version  Version hash for cache busting
     * @return string URL with version parameter
     */
    public function withVersion(string $url, ?string $version): string
    {
        if (empty($version)) {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return "{$url}{$separator}id={$version}";
    }

    /**
     * Build both CDN and origin URLs for API responses.
     *
     * @param  string  $path  Path relative to storage root
     * @return array{cdn: string, origin: string}
     */
    public function urls(string $path): array
    {
        return [
            'cdn' => $this->cdn($path),
            'origin' => $this->origin($path),
        ];
    }

    /**
     * Build all URL types for a path.
     *
     * @param  string  $path  Path relative to storage root
     * @return array{cdn: string, origin: string, private: string, apex: string}
     */
    public function allUrls(string $path): array
    {
        return [
            'cdn' => $this->cdn($path),
            'origin' => $this->origin($path),
            'private' => $this->private($path),
            'apex' => $this->apex($path),
        ];
    }

    /**
     * Build vBucket-scoped URLs for API responses.
     *
     * @param  string  $domain  The workspace domain for scoping
     * @param  string  $path  Path relative to storage root
     * @return array{cdn: string, origin: string, vbucket: string}
     */
    public function vBucketUrls(string $domain, string $path): array
    {
        $vBucketId = $this->vBucketId($domain);
        $scopedPath = "{$vBucketId}/{$path}";

        return [
            'cdn' => $this->cdn($scopedPath),
            'origin' => $this->origin($scopedPath),
            'vbucket' => $vBucketId,
        ];
    }

    /**
     * Build a URL from base URL and path.
     *
     * @param  string|null  $baseUrl  Base URL (falls back to apex if null)
     * @param  string  $path  Path to append
     * @return string Full URL
     */
    public function build(?string $baseUrl, string $path): string
    {
        if (empty($baseUrl)) {
            // Fallback to apex domain if no base URL configured
            $baseUrl = config('cdn.urls.apex', config('app.url'));
        }

        $baseUrl = rtrim($baseUrl, '/');
        $path = ltrim($path, '/');

        return "{$baseUrl}/{$path}";
    }

    /**
     * Build the base URL for signed private URLs.
     *
     * @return string Base URL for signed URLs
     */
    protected function buildSignedUrlBase(): string
    {
        $pullZone = config('cdn.bunny.private.pull_zone');

        // Support both full URL and just hostname in config
        if (str_starts_with($pullZone, 'https://') || str_starts_with($pullZone, 'http://')) {
            return rtrim($pullZone, '/');
        }

        return "https://{$pullZone}";
    }

    /**
     * Resolve expiry parameter to a Unix timestamp.
     *
     * @param  int|Carbon|null  $expiry  Expiry in seconds, Carbon instance, or null for config default
     * @return int Unix timestamp when the URL expires
     */
    protected function resolveExpiry(int|Carbon|null $expiry): int
    {
        if ($expiry instanceof Carbon) {
            return $expiry->timestamp;
        }

        $expirySeconds = $expiry ?? (int) config('cdn.signed_url_expiry', 3600);

        return time() + $expirySeconds;
    }

    /**
     * Get the path prefix for a content category.
     *
     * @param  string  $category  Category key from config (media, social, page, etc.)
     * @return string Path prefix
     */
    public function pathPrefix(string $category): string
    {
        return config("cdn.paths.{$category}", $category);
    }

    /**
     * Build a full path with category prefix.
     *
     * @param  string  $category  Category key
     * @param  string  $path  Relative path within category
     * @return string Full path with category prefix
     */
    public function categoryPath(string $category, string $path): string
    {
        $prefix = $this->pathPrefix($category);

        return "{$prefix}/{$path}";
    }
}
