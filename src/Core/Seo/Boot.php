<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Seo;

use Core\Seo\Analytics\SeoScoreTrend;
use Core\Seo\Console\Commands\AuditCanonicalUrls;
use Core\Seo\Console\Commands\RecordSeoScores;
use Core\Seo\Console\Commands\TestStructuredData;
use Core\Seo\Services\SchemaBuilderService;
use Core\Seo\Services\ServiceOgImageService;
use Core\Seo\Validation\CanonicalUrlValidator;
use Core\Seo\Validation\OgImageValidator;
use Core\Seo\Validation\StructuredDataTester;
use Illuminate\Support\ServiceProvider;

/**
 * SEO Module Service Provider.
 *
 * Provides SEO-related functionality:
 * - Schema.org structured data generation (Schema, SchemaBuilderService)
 * - Open Graph image generation and validation
 * - Canonical URL conflict detection
 * - SEO score trend tracking (SeoScoreTrend, SeoScoreHistory)
 * - Structured data testing (StructuredDataTester)
 *
 * Configuration options in config/seo.php:
 *
 * | Option | Default | Description |
 * |--------|---------|-------------|
 * | `trends.enabled` | true | Enable SEO score trend tracking |
 * | `trends.retention_days` | 90 | Days to retain historical scores |
 * | `trends.record_on_save` | true | Auto-record scores when metadata saved |
 * | `trends.min_interval_hours` | 24 | Minimum hours between recordings |
 * | `structured_data.external_validation` | false | Enable Google API validation |
 * | `structured_data.google_api_key` | null | Google Rich Results Test API key |
 * | `structured_data.cache_validation` | true | Cache validation results |
 * | `structured_data.cache_ttl` | 3600 | Cache TTL in seconds |
 *
 * Console commands:
 * - `seo:record-scores` - Record SEO scores for trend tracking
 * - `seo:test-structured-data` - Test structured data against schema.org
 * - `seo:audit-canonical` - Audit canonical URLs for conflicts
 */
class Boot extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register services as singletons
        $this->app->singleton(Schema::class);
        $this->app->singleton(SchemaBuilderService::class);
        $this->app->singleton(ServiceOgImageService::class);

        // Register validators
        $this->app->singleton(OgImageValidator::class);
        $this->app->singleton(CanonicalUrlValidator::class);
        $this->app->singleton(StructuredDataTester::class);

        // Register analytics services
        $this->app->singleton(SeoScoreTrend::class);

        // Register backward compatibility aliases
        $this->registerBackwardCompatAliases();
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->registerCommands();
    }

    /**
     * Register console commands.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                AuditCanonicalUrls::class,
                RecordSeoScores::class,
                TestStructuredData::class,
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
        // Schema (high-level JSON-LD generator)
        if (! class_exists(\App\Services\Content\SchemaService::class)) {
            class_alias(Schema::class, \App\Services\Content\SchemaService::class);
        }

        // Services
        if (! class_exists(\App\Services\SchemaBuilderService::class)) {
            class_alias(SchemaBuilderService::class, \App\Services\SchemaBuilderService::class);
        }

        if (! class_exists(\App\Services\ServiceOgImageService::class)) {
            class_alias(ServiceOgImageService::class, \App\Services\ServiceOgImageService::class);
        }
    }
}
