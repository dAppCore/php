<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Cdn;

use App\Facades\Cdn;
use App\Http\Middleware\RewriteOffloadedUrls;
use App\Jobs\PushAssetToCdn;
use App\Traits\HasCdnUrls;
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
use Core\Crypt\LthnHash;
use Core\Plug\Cdn\CdnManager;
use Core\Plug\Storage\StorageManager;
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
        if (class_exists(CdnManager::class)) {
            $this->app->singleton(CdnManager::class);
        }
        if (class_exists(StorageManager::class)) {
            $this->app->singleton(StorageManager::class);
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
            class_alias(LthnHash::class, \App\Services\Crypt\LthnHash::class);
        }

        // Models
        if (! class_exists(\App\Models\StorageOffload::class)) {
            class_alias(Models\StorageOffload::class, \App\Models\StorageOffload::class);
        }

        // Facades
        if (! class_exists(Cdn::class)) {
            class_alias(Facades\Cdn::class, Cdn::class);
        }

        // Traits
        if (! trait_exists(HasCdnUrls::class)) {
            class_alias(Traits\HasCdnUrls::class, HasCdnUrls::class);
        }

        // Middleware
        if (! class_exists(RewriteOffloadedUrls::class)) {
            class_alias(Middleware\RewriteOffloadedUrls::class, RewriteOffloadedUrls::class);
        }

        // Jobs
        if (! class_exists(PushAssetToCdn::class)) {
            class_alias(Jobs\PushAssetToCdn::class, PushAssetToCdn::class);
        }
    }
}
