<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Cdn;

use Core\Cdn\Console\CdnPurge;
use Core\Cdn\Console\OffloadMigrateCommand;
use Core\Cdn\Console\PushAssetsToCdn;
use Core\Cdn\Console\PushFluxToCdn;
use Core\Cdn\Services\AssetPipeline;
use Core\Cdn\Services\BunnyCdnService;
use Core\Cdn\Services\BunnyStorageService;
use Core\Cdn\Services\FluxCdnService;
use Core\Cdn\Services\StorageOffload;
use Core\Cdn\Services\StorageUrlResolver;
use Illuminate\Support\ServiceProvider;

/**
 * CDN Module Service Provider.
 *
 * Provides unified CDN and storage functionality:
 * - BunnyCDN pull zone operations (purging, stats)
 * - BunnyCDN storage zone operations (file upload/download)
 * - Context-aware URL resolution
 * - Asset processing pipeline
 * - vBucket workspace isolation using LTHN QuasiHash
 */
class Boot extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register configuration
        $this->mergeConfigFrom(__DIR__.'/config.php', 'cdn');
        $this->mergeConfigFrom(__DIR__.'/offload.php', 'offload');

        // Register Plug managers as singletons (when available)
        if (class_exists(\Core\Plug\Cdn\CdnManager::class)) {
            $this->app->singleton(\Core\Plug\Cdn\CdnManager::class);
        }
        if (class_exists(\Core\Plug\Storage\StorageManager::class)) {
            $this->app->singleton(\Core\Plug\Storage\StorageManager::class);
        }

        // Register legacy services as singletons (for backward compatibility)
        $this->app->singleton(BunnyCdnService::class);
        $this->app->singleton(BunnyStorageService::class);
        $this->app->singleton(StorageUrlResolver::class);
        $this->app->singleton(FluxCdnService::class);
        $this->app->singleton(AssetPipeline::class);
        $this->app->singleton(StorageOffload::class);

        // Register backward compatibility aliases
        $this->registerBackwardCompatAliases();
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register console commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                CdnPurge::class,
                PushAssetsToCdn::class,
                PushFluxToCdn::class,
                OffloadMigrateCommand::class,
            ]);
        }
    }

    /**
     * Register backward compatibility class aliases.
     *
     * These allow existing code using old namespaces to continue working
     * while we migrate to the new Core structure.
     */
    protected function registerBackwardCompatAliases(): void
    {
        // Services
        if (! class_exists(\App\Services\BunnyCdnService::class)) {
            class_alias(BunnyCdnService::class, \App\Services\BunnyCdnService::class);
        }

        if (! class_exists(\App\Services\Storage\BunnyStorageService::class)) {
            class_alias(BunnyStorageService::class, \App\Services\Storage\BunnyStorageService::class);
        }

        if (! class_exists(\App\Services\Storage\StorageUrlResolver::class)) {
            class_alias(StorageUrlResolver::class, \App\Services\Storage\StorageUrlResolver::class);
        }

        if (! class_exists(\App\Services\Storage\AssetPipeline::class)) {
            class_alias(AssetPipeline::class, \App\Services\Storage\AssetPipeline::class);
        }

        if (! class_exists(\App\Services\Storage\StorageOffload::class)) {
            class_alias(StorageOffload::class, \App\Services\Storage\StorageOffload::class);
        }

        if (! class_exists(\App\Services\Cdn\FluxCdnService::class)) {
            class_alias(FluxCdnService::class, \App\Services\Cdn\FluxCdnService::class);
        }

        // Crypt
        if (! class_exists(\App\Services\Crypt\LthnHash::class)) {
            class_alias(\Core\Crypt\LthnHash::class, \App\Services\Crypt\LthnHash::class);
        }

        // Models
        if (! class_exists(\App\Models\StorageOffload::class)) {
            class_alias(\Core\Cdn\Models\StorageOffload::class, \App\Models\StorageOffload::class);
        }

        // Facades
        if (! class_exists(\App\Facades\Cdn::class)) {
            class_alias(\Core\Cdn\Facades\Cdn::class, \App\Facades\Cdn::class);
        }

        // Traits
        if (! trait_exists(\App\Traits\HasCdnUrls::class)) {
            class_alias(\Core\Cdn\Traits\HasCdnUrls::class, \App\Traits\HasCdnUrls::class);
        }

        // Middleware
        if (! class_exists(\App\Http\Middleware\RewriteOffloadedUrls::class)) {
            class_alias(\Core\Cdn\Middleware\RewriteOffloadedUrls::class, \App\Http\Middleware\RewriteOffloadedUrls::class);
        }

        // Jobs
        if (! class_exists(\App\Jobs\PushAssetToCdn::class)) {
            class_alias(\Core\Cdn\Jobs\PushAssetToCdn::class, \App\Jobs\PushAssetToCdn::class);
        }
    }
}
