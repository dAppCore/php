<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Cdn\Services;

use Core\Helpers\Cdn;
use Flux\Flux;

/**
 * CDN-aware Flux asset service.
 *
 * In development: Uses standard Laravel routes (/flux/flux.js)
 * In production: Uses CDN URLs (cdn.host.uk.com/flux/flux.min.js)
 *
 * Requires Flux assets to be uploaded to CDN storage zone.
 *
 * URL building is delegated to CdnUrlBuilder for consistency across services.
 *
 * ## Methods
 *
 * | Method | Returns | Description |
 * |--------|---------|-------------|
 * | `scripts()` | `string` | Get Flux scripts tag with CDN awareness |
 * | `editorScripts()` | `string` | Get Flux editor scripts (Pro only) |
 * | `editorStyles()` | `string` | Get Flux editor styles (Pro only) |
 * | `shouldUseCdn()` | `bool` | Check if CDN should be used |
 * | `getCdnAssetPaths()` | `array<string, string>` | Get source-to-CDN path mapping |
 *
 * @see CdnUrlBuilder For the underlying URL building logic
 */
class FluxCdnService
{
    protected CdnUrlBuilder $urlBuilder;

    public function __construct(?CdnUrlBuilder $urlBuilder = null)
    {
        $this->urlBuilder = $urlBuilder ?? new CdnUrlBuilder;
    }

    /**
     * Get the Flux scripts tag with CDN awareness.
     *
     * @param  array<string, mixed>  $options  Options like ['nonce' => 'abc123']
     * @return string HTML script tag
     */
    public function scripts(array $options = []): string
    {
        $nonce = isset($options['nonce']) ? ' nonce="'.$options['nonce'].'"' : '';

        // Use CDN when enabled (respects CDN_FORCE_LOCAL for testing)
        if (! $this->shouldUseCdn()) {
            return app('flux')->scripts($options);
        }

        // In production, use CDN URL (no vBucket - shared platform asset)
        $versionHash = $this->getVersionHash();
        $filename = config('app.debug') ? 'flux.js' : 'flux.min.js';
        $url = $this->cdnUrl("flux/{$filename}", $versionHash);

        return '<script src="'.$url.'" data-navigate-once'.$nonce.'></script>';
    }

    /**
     * Get the Flux editor scripts tag with CDN awareness.
     *
     * @return string HTML script tag for Flux editor
     *
     * @throws \Exception When Flux Pro is not available
     */
    public function editorScripts(): string
    {
        if (! Flux::pro()) {
            throw new \Exception('Flux Pro is required to use the Flux editor.');
        }

        // Use CDN when enabled (respects CDN_FORCE_LOCAL for testing)
        if (! $this->shouldUseCdn()) {
            return \Flux\AssetManager::editorScripts();
        }

        // In production, use CDN URL (no vBucket - shared platform asset)
        $versionHash = $this->getVersionHash('/editor.js');
        $filename = config('app.debug') ? 'editor.js' : 'editor.min.js';
        $url = $this->cdnUrl("flux/{$filename}", $versionHash);

        return '<script src="'.$url.'" defer></script>';
    }

    /**
     * Get the Flux editor styles tag with CDN awareness.
     *
     * @return string HTML link tag for Flux editor styles
     *
     * @throws \Exception When Flux Pro is not available
     */
    public function editorStyles(): string
    {
        if (! Flux::pro()) {
            throw new \Exception('Flux Pro is required to use the Flux editor.');
        }

        // Use CDN when enabled (respects CDN_FORCE_LOCAL for testing)
        if (! $this->shouldUseCdn()) {
            return \Flux\AssetManager::editorStyles();
        }

        // In production, use CDN URL (no vBucket - shared platform asset)
        $versionHash = $this->getVersionHash('/editor.css');
        $url = $this->cdnUrl('flux/editor.css', $versionHash);

        return '<link rel="stylesheet" href="'.$url.'">';
    }

    /**
     * Get version hash from Flux manifest.
     *
     * @param  string  $key  Manifest key to look up
     * @return string 8-character hash for cache busting
     */
    protected function getVersionHash(string $key = '/flux.js'): string
    {
        $manifestPath = Flux::pro()
            ? base_path('vendor/admin/flux-pro/dist/manifest.json')
            : base_path('vendor/admin/flux/dist/manifest.json');

        if (! file_exists($manifestPath)) {
            return substr(md5(config('app.version', '1.0')), 0, 8);
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);

        return $manifest[$key] ?? substr(md5(config('app.version', '1.0')), 0, 8);
    }

    /**
     * Check if we should use CDN for Flux assets.
     *
     * Respects CDN_FORCE_LOCAL for testing.
     *
     * @return bool True if CDN should be used, false for local assets
     */
    public function shouldUseCdn(): bool
    {
        return Cdn::isEnabled();
    }

    /**
     * Build CDN URL for shared platform assets (no vBucket scoping).
     *
     * Flux assets are shared across all workspaces, so they don't use
     * workspace-specific vBucket prefixes.
     *
     * @param  string  $path  Asset path relative to CDN root
     * @param  string|null  $version  Optional version hash for cache busting
     * @return string Full CDN URL with optional version query parameter
     */
    protected function cdnUrl(string $path, ?string $version = null): string
    {
        $cdnUrl = config('cdn.urls.cdn');

        if (empty($cdnUrl)) {
            $baseUrl = asset($path);

            return $this->urlBuilder->withVersion($baseUrl, $version);
        }

        $url = $this->urlBuilder->cdn($path);

        return $this->urlBuilder->withVersion($url, $version);
    }

    /**
     * Get the list of Flux files that should be uploaded to CDN.
     *
     * @return array<string, string> Map of source path => CDN path
     */
    public function getCdnAssetPaths(): array
    {
        $basePath = Flux::pro()
            ? base_path('vendor/admin/flux-pro/dist')
            : base_path('vendor/admin/flux/dist');

        $files = [
            "{$basePath}/flux.js" => 'flux/flux.js',
            "{$basePath}/flux.min.js" => 'flux/flux.min.js',
        ];

        // Add editor files for Pro
        if (Flux::pro()) {
            $files["{$basePath}/editor.js"] = 'flux/editor.js';
            $files["{$basePath}/editor.min.js"] = 'flux/editor.min.js';
            $files["{$basePath}/editor.css"] = 'flux/editor.css';
        }

        return $files;
    }
}
