<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Media;

use Core\Events\WebRoutesRegistering;
use Core\Media\Thumbnail\LazyThumbnail;
use Illuminate\Support\ServiceProvider;

/**
 * Media Module Service Provider.
 *
 * Provides media processing functionality:
 * - Image optimization and compression
 * - Media conversions (thumbnails, resizing)
 * - Lazy thumbnail generation
 *
 * ## Lazy Thumbnails
 *
 * Thumbnails are generated on-demand when first requested, rather than
 * eagerly on upload. This improves upload performance and reduces storage
 * for unused thumbnail sizes.
 *
 * Configure via `config/images.php` under `lazy_thumbnails`:
 * - `enabled` - Enable/disable lazy generation
 * - `queue_threshold_kb` - Size threshold for queueing (default: 500KB)
 * - `cache_ttl` - How long to cache thumbnail paths (default: 24 hours)
 * - `placeholder` - Custom placeholder image path or URL
 *
 * Usage:
 * ```php
 * $lazyThumb = app(LazyThumbnail::class);
 * $url = $lazyThumb->url('uploads/image.jpg', 200, 200);
 * ```
 *
 * Or via URL directly:
 * ```
 * /media/thumb?path=BASE64_PATH&w=200&h=200&sig=SIGNATURE
 * ```
 */
class Boot extends ServiceProvider
{
    /**
     * Lifecycle events this module listens to.
     *
     * @var array<class-string, string>
     */
    public static array $listens = [
        WebRoutesRegistering::class => 'onWebRoutes',
    ];

    /**
     * Register services.
     */
    public function register(): void
    {
        // Register configuration
        $this->mergeConfigFrom(__DIR__.'/config.php', 'images');

        // Register LazyThumbnail as singleton
        $this->app->singleton(LazyThumbnail::class, function () {
            return new LazyThumbnail;
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Register web routes for media endpoints.
     */
    public function onWebRoutes(WebRoutesRegistering $event): void
    {
        // Only register routes if lazy thumbnails are enabled
        if (config('images.lazy_thumbnails.enabled', true)) {
            $event->routes(fn () => require __DIR__.'/Routes/web.php');
        }
    }
}
