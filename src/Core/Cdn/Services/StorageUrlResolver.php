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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Context-aware URL resolver for CDN/storage architecture.
 *
 * Provides intelligent URL resolution based on request context:
 * - Admin/internal requests -> Origin URLs (Hetzner)
 * - Public/embed requests -> CDN URLs (BunnyCDN)
 * - API requests -> Both URLs returned
 *
 * Supports vBucket scoping for workspace-isolated CDN paths using LTHN QuasiHash.
 *
 * URL building is delegated to CdnUrlBuilder for consistency across services.
 *
 * ## Methods
 *
 * | Method | Returns | Description |
 * |--------|---------|-------------|
 * | `vBucketId()` | `string` | Generate vBucket ID for a domain |
 * | `vBucketCdn()` | `string` | Get CDN URL with vBucket scoping |
 * | `vBucketOrigin()` | `string` | Get origin URL with vBucket scoping |
 * | `vBucketPath()` | `string` | Build vBucket-scoped storage path |
 * | `vBucketUrls()` | `array` | Get both URLs with vBucket scoping |
 * | `cdn()` | `string` | Get CDN delivery URL for a path |
 * | `origin()` | `string` | Get origin URL (Hetzner) for a path |
 * | `private()` | `string` | Get private storage URL for a path |
 * | `signedUrl()` | `string\|null` | Get signed URL for private content |
 * | `apex()` | `string` | Get apex domain URL for a path |
 * | `asset()` | `string` | Get context-aware URL for a path |
 * | `urls()` | `array` | Get both CDN and origin URLs |
 * | `allUrls()` | `array` | Get all URLs (cdn, origin, private, apex) |
 * | `detectContext()` | `string` | Detect current request context |
 * | `isAdminContext()` | `bool` | Check if current context is admin |
 * | `pushToCdn()` | `bool` | Push a file to CDN storage zone |
 * | `deleteFromCdn()` | `bool` | Delete a file from CDN storage zone |
 * | `purge()` | `bool` | Purge a path from CDN cache |
 * | `cachedAsset()` | `string` | Get cached CDN URL with intelligent caching |
 * | `publicDisk()` | `Filesystem` | Get the public storage disk |
 * | `privateDisk()` | `Filesystem` | Get the private storage disk |
 * | `storePublic()` | `bool` | Store file to public bucket |
 * | `storePrivate()` | `bool` | Store file to private bucket |
 * | `deleteAsset()` | `bool` | Delete file from storage and CDN |
 * | `pathPrefix()` | `string` | Get path prefix for a category |
 * | `categoryPath()` | `string` | Build full path with category prefix |
 *
 * @see CdnUrlBuilder For the underlying URL building logic
 */
class StorageUrlResolver
{
    protected BunnyStorageService $bunnyStorage;

    protected CdnUrlBuilder $urlBuilder;

    public function __construct(BunnyStorageService $bunnyStorage, ?CdnUrlBuilder $urlBuilder = null)
    {
        $this->bunnyStorage = $bunnyStorage;
        $this->urlBuilder = $urlBuilder ?? new CdnUrlBuilder;
    }

    /**
     * Get the URL builder instance.
     */
    public function getUrlBuilder(): CdnUrlBuilder
    {
        return $this->urlBuilder;
    }

    /**
     * Generate a vBucket ID for a domain/workspace.
     *
     * Uses LTHN QuasiHash protocol for deterministic, scoped identifiers.
     * Format: cdn.host.uk.com/{vBucketId}/path/to/asset.js
     *
     * @param  string  $domain  The domain name (e.g., "host.uk.com")
     * @return string 16-character vBucket identifier
     */
    public function vBucketId(string $domain): string
    {
        return $this->urlBuilder->vBucketId($domain);
    }

    /**
     * Get CDN URL with vBucket scoping for workspace isolation.
     *
     * @param  string  $domain  The workspace domain for scoping
     * @param  string  $path  Path relative to storage root
     */
    public function vBucketCdn(string $domain, string $path): string
    {
        return $this->urlBuilder->vBucket($domain, $path);
    }

    /**
     * Get origin URL with vBucket scoping for workspace isolation.
     *
     * @param  string  $domain  The workspace domain for scoping
     * @param  string  $path  Path relative to storage root
     */
    public function vBucketOrigin(string $domain, string $path): string
    {
        $scopedPath = $this->urlBuilder->vBucketPath($domain, $path);

        return $this->urlBuilder->origin($scopedPath);
    }

    /**
     * Build a vBucket-scoped storage path.
     *
     * @param  string  $domain  The workspace domain for scoping
     * @param  string  $path  Path relative to vBucket root
     */
    public function vBucketPath(string $domain, string $path): string
    {
        return $this->urlBuilder->vBucketPath($domain, $path);
    }

    /**
     * Get both URLs with vBucket scoping for API responses.
     *
     * @param  string  $domain  The workspace domain for scoping
     * @param  string  $path  Path relative to storage root
     * @return array{cdn: string, origin: string, vbucket: string}
     */
    public function vBucketUrls(string $domain, string $path): array
    {
        return $this->urlBuilder->vBucketUrls($domain, $path);
    }

    /**
     * Get the CDN delivery URL for a path.
     * Always returns the BunnyCDN pull zone URL.
     *
     * @param  string  $path  Path relative to storage root
     */
    public function cdn(string $path): string
    {
        return $this->urlBuilder->cdn($path);
    }

    /**
     * Get the origin URL for a path (Hetzner public bucket).
     * Direct access to origin storage, bypassing CDN.
     *
     * @param  string  $path  Path relative to storage root
     */
    public function origin(string $path): string
    {
        return $this->urlBuilder->origin($path);
    }

    /**
     * Get the private storage URL for a path.
     * For DRM/gated content - not publicly accessible.
     *
     * @param  string  $path  Path relative to storage root
     */
    public function private(string $path): string
    {
        return $this->urlBuilder->private($path);
    }

    /**
     * Get a signed URL for private CDN content with token authentication.
     * Generates time-limited access URLs for gated/DRM content.
     *
     * @param  string  $path  Path relative to storage root
     * @param  int|Carbon|null  $expiry  Expiry time in seconds, or a Carbon instance for absolute expiry.
     *                                   Defaults to config('cdn.signed_url_expiry', 3600) when null.
     * @return string|null Signed URL or null if token not configured
     */
    public function signedUrl(string $path, int|Carbon|null $expiry = null): ?string
    {
        return $this->urlBuilder->signed($path, $expiry);
    }

    /**
     * Build the base URL for signed private URLs.
     * Uses config for the private pull zone URL.
     *
     * @deprecated Use CdnUrlBuilder::signed() instead
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
     * Get the apex domain URL for a path.
     * Fallback for assets served through main domain.
     *
     * @param  string  $path  Path relative to web root
     */
    public function apex(string $path): string
    {
        return $this->urlBuilder->apex($path);
    }

    /**
     * Get context-aware URL for a path.
     * Automatically determines whether to return CDN or origin URL.
     *
     * @param  string  $path  Path relative to storage root
     * @param  string|null  $context  Force context ('admin', 'public', or null for auto)
     */
    public function asset(string $path, ?string $context = null): string
    {
        $context = $context ?? $this->detectContext();

        return $this->urlBuilder->asset($path, $context);
    }

    /**
     * Get both CDN and origin URLs for API responses.
     *
     * @param  string  $path  Path relative to storage root
     * @return array{cdn: string, origin: string}
     */
    public function urls(string $path): array
    {
        return $this->urlBuilder->urls($path);
    }

    /**
     * Get all URLs for a path (including private and apex).
     *
     * @param  string  $path  Path relative to storage root
     * @return array{cdn: string, origin: string, private: string, apex: string}
     */
    public function allUrls(string $path): array
    {
        return $this->urlBuilder->allUrls($path);
    }

    /**
     * Detect the current request context based on headers and route.
     *
     * Checks for admin headers and route prefixes to determine context.
     *
     * @return string 'admin' or 'public'
     */
    public function detectContext(): string
    {
        // Check for admin headers
        foreach (config('cdn.context.admin_headers', []) as $header) {
            if (Request::hasHeader($header)) {
                return 'admin';
            }
        }

        // Check for admin route prefixes
        $path = Request::path();
        foreach (config('cdn.context.admin_prefixes', []) as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return 'admin';
            }
        }

        return config('cdn.context.default', 'public');
    }

    /**
     * Check if the current context is admin/internal.
     *
     * @return bool True if in admin context
     */
    public function isAdminContext(): bool
    {
        return $this->detectContext() === 'admin';
    }

    /**
     * Push a file to the CDN storage zone.
     *
     * @param  string  $disk  Laravel disk name ('hetzner-public' or 'hetzner-private')
     * @param  string  $path  Path within the disk
     * @param  string  $zone  Target zone ('public' or 'private')
     */
    public function pushToCdn(string $disk, string $path, string $zone = 'public'): bool
    {
        if (! $this->bunnyStorage->isPushEnabled()) {
            return false;
        }

        return $this->bunnyStorage->copyFromDisk($disk, $path, $zone);
    }

    /**
     * Delete a file from the CDN storage zone.
     *
     * @param  string  $path  Path within the storage zone
     * @param  string  $zone  Target zone ('public' or 'private')
     */
    public function deleteFromCdn(string $path, string $zone = 'public'): bool
    {
        return $this->bunnyStorage->delete($path, $zone);
    }

    /**
     * Purge a path from the CDN cache.
     * Uses the existing BunnyCdnService for pull zone API.
     *
     * @param  string  $path  Path to purge
     */
    public function purge(string $path): bool
    {
        // Use existing BunnyCdnService for pull zone operations
        $bunnyCdnService = app(BunnyCdnService::class);

        if (! $bunnyCdnService->isConfigured()) {
            return false;
        }

        return $bunnyCdnService->purgeUrl($this->cdn($path));
    }

    /**
     * Get cached CDN URL with intelligent caching.
     *
     * @param  string  $path  Path relative to storage root
     * @param  string|null  $context  Force context ('admin', 'public', or null for auto)
     */
    public function cachedAsset(string $path, ?string $context = null): string
    {
        if (! config('cdn.cache.enabled', true)) {
            return $this->asset($path, $context);
        }

        $context = $context ?? $this->detectContext();
        $cacheKey = config('cdn.cache.prefix', 'cdn_url').':'.$context.':'.md5($path);
        $ttl = config('cdn.cache.ttl', 3600);

        return Cache::remember($cacheKey, $ttl, fn () => $this->asset($path, $context));
    }

    /**
     * Build a URL from base URL and path.
     *
     * @param  string|null  $baseUrl  Base URL (falls back to apex if null)
     * @param  string  $path  Path to append
     * @return string Full URL
     *
     * @deprecated Use CdnUrlBuilder::build() instead
     */
    protected function buildUrl(?string $baseUrl, string $path): string
    {
        return $this->urlBuilder->build($baseUrl, $path);
    }

    /**
     * Get the public storage disk.
     *
     * @return \Illuminate\Contracts\Filesystem\Filesystem
     */
    public function publicDisk()
    {
        return Storage::disk(config('cdn.disks.public', 'hetzner-public'));
    }

    /**
     * Get the private storage disk.
     *
     * @return \Illuminate\Contracts\Filesystem\Filesystem
     */
    public function privateDisk()
    {
        return Storage::disk(config('cdn.disks.private', 'hetzner-private'));
    }

    /**
     * Store file to public bucket and optionally push to CDN.
     *
     * @param  string  $path  Target path
     * @param  string|resource  $contents  File contents or stream
     * @param  bool  $pushToCdn  Whether to also push to BunnyCDN storage zone
     */
    public function storePublic(string $path, $contents, bool $pushToCdn = true): bool
    {
        $stored = $this->publicDisk()->put($path, $contents);

        if ($stored && $pushToCdn && config('cdn.pipeline.auto_push', true)) {
            // Queue the push if configured, otherwise push synchronously
            if ($queue = config('cdn.pipeline.queue')) {
                dispatch(new \Core\Cdn\Jobs\PushAssetToCdn('hetzner-public', $path, 'public'))->onQueue($queue);
            } else {
                $this->pushToCdn('hetzner-public', $path, 'public');
            }
        }

        return $stored;
    }

    /**
     * Store file to private bucket and optionally push to CDN.
     *
     * @param  string  $path  Target path
     * @param  string|resource  $contents  File contents or stream
     * @param  bool  $pushToCdn  Whether to also push to BunnyCDN storage zone
     */
    public function storePrivate(string $path, $contents, bool $pushToCdn = true): bool
    {
        $stored = $this->privateDisk()->put($path, $contents);

        if ($stored && $pushToCdn && config('cdn.pipeline.auto_push', true)) {
            if ($queue = config('cdn.pipeline.queue')) {
                dispatch(new \Core\Cdn\Jobs\PushAssetToCdn('hetzner-private', $path, 'private'))->onQueue($queue);
            } else {
                $this->pushToCdn('hetzner-private', $path, 'private');
            }
        }

        return $stored;
    }

    /**
     * Delete file from storage and CDN.
     *
     * @param  string  $path  File path
     * @param  string  $bucket  'public' or 'private'
     */
    public function deleteAsset(string $path, string $bucket = 'public'): bool
    {
        $disk = $bucket === 'private' ? $this->privateDisk() : $this->publicDisk();
        $deleted = $disk->delete($path);

        if ($deleted) {
            $this->deleteFromCdn($path, $bucket);

            if (config('cdn.pipeline.auto_purge', true)) {
                $this->purge($path);
            }
        }

        return $deleted;
    }

    /**
     * Get the path prefix for a content category.
     *
     * @param  string  $category  Category key from config (media, social, page, etc.)
     */
    public function pathPrefix(string $category): string
    {
        return $this->urlBuilder->pathPrefix($category);
    }

    /**
     * Build a full path with category prefix.
     *
     * @param  string  $category  Category key
     * @param  string  $path  Relative path within category
     */
    public function categoryPath(string $category, string $path): string
    {
        return $this->urlBuilder->categoryPath($category, $path);
    }

    /**
     * Resolve expiry parameter to a Unix timestamp.
     *
     * @param  int|Carbon|null  $expiry  Expiry in seconds, Carbon instance, or null for config default
     * @return int Unix timestamp when the URL expires
     *
     * @deprecated Use CdnUrlBuilder internally instead
     */
    protected function resolveExpiry(int|Carbon|null $expiry): int
    {
        if ($expiry instanceof Carbon) {
            return $expiry->timestamp;
        }

        $expirySeconds = $expiry ?? (int) config('cdn.signed_url_expiry', 3600);

        return time() + $expirySeconds;
    }
}
