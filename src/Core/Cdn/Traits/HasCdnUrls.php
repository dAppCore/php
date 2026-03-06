<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Cdn\Traits;

use Core\Cdn\Facades\Cdn;

/**
 * Trait for models that have asset paths needing CDN URL resolution.
 *
 * Models using this trait should define a $cdnPathAttribute property
 * specifying which attribute contains the storage path.
 *
 * Example:
 *   protected string $cdnPathAttribute = 'path';
 *   protected string $cdnBucket = 'public'; // 'public' or 'private'
 */
trait HasCdnUrls
{
    /**
     * Get the CDN delivery URL for this model's asset.
     */
    public function getCdnUrl(): ?string
    {
        $path = $this->getCdnPath();

        return $path ? Cdn::cdn($path) : null;
    }

    /**
     * Get the origin storage URL (Hetzner) for this model's asset.
     */
    public function getOriginUrl(): ?string
    {
        $path = $this->getCdnPath();

        return $path ? Cdn::origin($path) : null;
    }

    /**
     * Get context-aware URL for this model's asset.
     * Returns CDN URL for public context, origin URL for admin context.
     *
     * @param  string|null  $context  Force context ('admin', 'public', or null for auto)
     */
    public function getAssetUrl(?string $context = null): ?string
    {
        $path = $this->getCdnPath();

        return $path ? Cdn::asset($path, $context) : null;
    }

    /**
     * Get both CDN and origin URLs for API responses.
     *
     * @return array{cdn: string|null, origin: string|null}
     */
    public function getAssetUrls(): array
    {
        $path = $this->getCdnPath();

        if (! $path) {
            return ['cdn' => null, 'origin' => null];
        }

        return Cdn::urls($path);
    }

    /**
     * Get all URLs for this model's asset (including private and apex).
     *
     * @return array{cdn: string|null, origin: string|null, private: string|null, apex: string|null}
     */
    public function getAllAssetUrls(): array
    {
        $path = $this->getCdnPath();

        if (! $path) {
            return ['cdn' => null, 'origin' => null, 'private' => null, 'apex' => null];
        }

        return Cdn::allUrls($path);
    }

    /**
     * Get the storage path for this model's asset.
     */
    public function getCdnPath(): ?string
    {
        $attribute = $this->getCdnPathAttribute();

        return $this->{$attribute} ?? null;
    }

    /**
     * Get the attribute name containing the storage path.
     * Override this method or set $cdnPathAttribute property.
     */
    protected function getCdnPathAttribute(): string
    {
        return $this->cdnPathAttribute ?? 'path';
    }

    /**
     * Get the bucket type for this model's assets.
     * Override this method or set $cdnBucket property.
     *
     * @return string 'public' or 'private'
     */
    public function getCdnBucket(): string
    {
        return $this->cdnBucket ?? 'public';
    }

    /**
     * Push this model's asset to the CDN storage zone.
     */
    public function pushToCdn(): bool
    {
        $path = $this->getCdnPath();

        if (! $path) {
            return false;
        }

        $bucket = $this->getCdnBucket();
        $disk = $bucket === 'private' ? 'hetzner-private' : 'hetzner-public';

        return Cdn::pushToCdn($disk, $path, $bucket);
    }

    /**
     * Delete this model's asset from the CDN storage zone.
     */
    public function deleteFromCdn(): bool
    {
        $path = $this->getCdnPath();

        if (! $path) {
            return false;
        }

        return Cdn::deleteFromCdn($path, $this->getCdnBucket());
    }

    /**
     * Purge this model's asset from the CDN cache.
     */
    public function purgeFromCdn(): bool
    {
        $path = $this->getCdnPath();

        if (! $path) {
            return false;
        }

        return Cdn::purge($path);
    }

    /**
     * Scope to append CDN URLs to the model when converting to array/JSON.
     * Add this to $appends property: 'cdn_url', 'origin_url', 'asset_urls'
     */
    public function getCdnUrlAttribute(): ?string
    {
        return $this->getCdnUrl();
    }

    public function getOriginUrlAttribute(): ?string
    {
        return $this->getOriginUrl();
    }

    public function getAssetUrlsAttribute(): array
    {
        return $this->getAssetUrls();
    }
}
