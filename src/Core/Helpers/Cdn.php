<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Helpers;

/**
 * CDN helper for asset URLs.
 *
 * Provides simple asset URL generation with optional CDN subdomain support.
 * Works locally with Valet wildcard (cdn.core.test) or with external CDN in production.
 */
class Cdn
{
    /**
     * Get asset URL, optionally through CDN subdomain.
     *
     * In development: Uses cdn.{domain} subdomain for CDN-like caching
     * In production: Uses configured CDN URL or falls back to cdn subdomain
     *
     * @param  string  $path  Asset path (e.g., 'js/app.js', 'images/logo.svg')
     * @param  string|null  $version  Optional version for cache busting
     */
    public static function asset(string $path, ?string $version = null): string
    {
        $url = self::buildUrl($path);

        return $version ? "{$url}?v={$version}" : $url;
    }

    /**
     * Get versioned asset URL with automatic cache busting.
     *
     * Uses file hash for cache busting - changes when file changes.
     *
     * @param  string  $path  Asset path
     */
    public static function versioned(string $path): string
    {
        $version = self::getFileVersion($path);

        return self::asset($path, $version);
    }

    /**
     * Check if CDN mode is enabled.
     *
     * Requires explicit CDN_ENABLED=true in .env.
     * Without it, all assets use the standard asset() helper.
     */
    public static function isEnabled(): bool
    {
        return (bool) config('cdn.enabled', false);
    }

    /**
     * Get the CDN base URL.
     *
     * Returns the external CDN URL if configured,
     * otherwise builds the local CDN subdomain URL.
     */
    public static function getBaseUrl(): string
    {
        // External CDN configured
        if (config('cdn.enabled') && $cdnUrl = config('cdn.urls.cdn')) {
            return rtrim($cdnUrl, '/');
        }

        // Build local CDN subdomain URL
        $subdomain = config('core.cdn.subdomain', 'cdn');
        $baseDomain = config('core.domain.base', 'core.test');
        $scheme = request()->secure() ? 'https' : 'http';

        return "{$scheme}://{$subdomain}.{$baseDomain}";
    }

    /**
     * Get origin URL (non-CDN).
     *
     * Useful when you need the direct URL without CDN.
     *
     * @param  string  $path  Asset path
     */
    public static function origin(string $path): string
    {
        return asset($path);
    }

    /**
     * Build the full URL for a path.
     */
    protected static function buildUrl(string $path): string
    {
        // If CDN is disabled, use standard asset helper
        if (! self::isEnabled()) {
            return asset($path);
        }

        $baseUrl = self::getBaseUrl();
        $path = ltrim($path, '/');

        return "{$baseUrl}/{$path}";
    }

    /**
     * Get file version hash for cache busting.
     */
    protected static function getFileVersion(string $path): string
    {
        $fullPath = public_path($path);

        if (file_exists($fullPath)) {
            return substr(md5_file($fullPath), 0, 8);
        }

        // Fallback to app version
        return substr(md5(config('app.version', '1.0.0')), 0, 8);
    }
}
