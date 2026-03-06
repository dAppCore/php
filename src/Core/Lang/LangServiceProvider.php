<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Lang;

use Core\Lang\Console\Commands\TranslationCoverageCommand;
use Core\Lang\Console\Commands\TranslationMemoryCommand;
use Core\Lang\Coverage\TranslationCoverage;
use Core\Lang\TranslationMemory\Contracts\TranslationMemoryRepository;
use Core\Lang\TranslationMemory\JsonTranslationMemoryRepository;
use Core\Lang\TranslationMemory\TranslationMemory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Illuminate\Translation\Translator;

/**
 * Core Language Service Provider.
 *
 * Provides enhanced translation functionality:
 * - Automatic discovery via Laravel's package discovery
 * - Fallback locale chain support (e.g., en_GB -> en -> fallback)
 * - Translation key validation with development warnings
 * - Translation memory with fuzzy matching and TMX import/export
 *
 * Configuration options in config/core.php:
 *   'lang' => [
 *       'fallback_chain' => true,           // Enable locale chain fallback
 *       'validate_keys' => true,            // Warn about missing keys in dev
 *       'log_missing_keys' => true,         // Log missing keys
 *       'missing_key_log_level' => 'debug', // Log level for missing keys
 *       'icu_enabled' => true,              // Enable ICU message format support
 *       'translation_memory' => [
 *           'enabled' => true,
 *           'storage_path' => storage_path('framework/translation-memory'),
 *           'driver' => 'json', // json, database
 *           'fuzzy' => [
 *               'min_similarity' => 0.6,
 *               'max_results' => 10,
 *               'algorithm' => 'combined', // levenshtein, token, ngram, combined
 *           ],
 *       ],
 *   ]
 */
class LangServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->registerIcuFormatter();
        $this->registerCoverageService();
        $this->registerTranslationMemory();
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadTranslations();
        $this->publishTranslations();
        $this->setupFallbackChain();
        $this->setupMissingKeyValidation();
        $this->registerCommands();
    }

    /**
     * Register the ICU message formatter.
     */
    protected function registerIcuFormatter(): void
    {
        $this->app->singleton(IcuMessageFormatter::class, function (Application $app) {
            return new IcuMessageFormatter($app->getLocale());
        });

        // Register an alias for easier access
        $this->app->alias(IcuMessageFormatter::class, 'icu.formatter');
    }

    /**
     * Register the translation coverage service.
     */
    protected function registerCoverageService(): void
    {
        $this->app->singleton(TranslationCoverage::class);
    }

    /**
     * Register the translation memory service.
     */
    protected function registerTranslationMemory(): void
    {
        // Register the repository
        $this->app->singleton(TranslationMemoryRepository::class, function (Application $app) {
            $driver = config('core.lang.translation_memory.driver', 'json');

            if ($driver === 'json') {
                $storagePath = config(
                    'core.lang.translation_memory.storage_path',
                    storage_path('framework/translation-memory')
                );

                return new JsonTranslationMemoryRepository($storagePath);
            }

            // Default to JSON repository
            return new JsonTranslationMemoryRepository(
                storage_path('framework/translation-memory')
            );
        });

        // Register the main service
        $this->app->singleton(TranslationMemory::class, function (Application $app) {
            return new TranslationMemory(
                $app->make(TranslationMemoryRepository::class)
            );
        });

        // Register an alias for easier access
        $this->app->alias(TranslationMemory::class, 'translation.memory');
    }

    /**
     * Register console commands.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                TranslationCoverageCommand::class,
                TranslationMemoryCommand::class,
            ]);
        }
    }

    /**
     * Load translation files from the Lang directory.
     */
    protected function loadTranslations(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/en_GB', 'core');

        // Also register translations under the base locale (en) for fallback
        if (is_dir(__DIR__.'/en')) {
            $this->loadTranslationsFrom(__DIR__.'/en', 'core');
        }
    }

    /**
     * Publish translation files for customisation.
     */
    protected function publishTranslations(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/en_GB' => $this->app->langPath('vendor/core/en_GB'),
            ], 'core-translations');

            // Publish base en translations if they exist
            if (is_dir(__DIR__.'/en')) {
                $this->publishes([
                    __DIR__.'/en' => $this->app->langPath('vendor/core/en'),
                ], 'core-translations');
            }
        }
    }

    /**
     * Set up fallback locale chain support.
     *
     * This enables a chain like: en_GB -> en -> fallback
     * So regional locales can fall back to their base locale first.
     */
    protected function setupFallbackChain(): void
    {
        if (! config('core.lang.fallback_chain', true)) {
            return;
        }

        /** @var Translator $translator */
        $translator = $this->app->make('translator');

        $translator->determineLocalesUsing(function (array $locales) use ($translator) {
            return $this->buildFallbackChain($locales, $translator->getFallback());
        });
    }

    /**
     * Build a fallback chain from the given locales.
     *
     * For example, 'en_GB' with fallback 'en' produces: ['en_GB', 'en']
     * For 'de_AT' with fallback 'en' produces: ['de_AT', 'de', 'en']
     *
     * @param  array<string>  $locales  Initial locales from Laravel
     * @param  string|null  $fallback  The configured fallback locale
     * @return array<string> The expanded locale chain
     */
    protected function buildFallbackChain(array $locales, ?string $fallback): array
    {
        $chain = [];
        $seen = [];

        foreach ($locales as $locale) {
            // Add the locale itself
            if (! isset($seen[$locale])) {
                $chain[] = $locale;
                $seen[$locale] = true;
            }

            // Extract base locale (e.g., 'en' from 'en_GB')
            $baseLocale = $this->extractBaseLocale($locale);

            if ($baseLocale !== null && $baseLocale !== $locale && ! isset($seen[$baseLocale])) {
                $chain[] = $baseLocale;
                $seen[$baseLocale] = true;
            }
        }

        // Ensure the fallback is always at the end if not already included
        if ($fallback !== null && ! isset($seen[$fallback])) {
            $chain[] = $fallback;
        }

        return $chain;
    }

    /**
     * Extract the base locale from a regional locale.
     *
     * @param  string  $locale  The locale (e.g., 'en_GB', 'en-GB', 'en')
     * @return string|null The base locale (e.g., 'en') or null if not applicable
     */
    protected function extractBaseLocale(string $locale): ?string
    {
        // Handle both underscore (en_GB) and hyphen (en-GB) formats
        if (preg_match('/^([a-z]{2,3})[-_][A-Z]{2}$/i', $locale, $matches)) {
            return strtolower($matches[1]);
        }

        return null;
    }

    /**
     * Set up translation key validation for development.
     *
     * This registers a callback to handle missing translation keys,
     * which can log warnings or take other actions in development.
     */
    protected function setupMissingKeyValidation(): void
    {
        // Only enable validation if configured (defaults to true in local/development)
        $validateKeys = config('core.lang.validate_keys', $this->app->environment('local', 'development', 'testing'));

        if (! $validateKeys) {
            return;
        }

        /** @var Translator $translator */
        $translator = $this->app->make('translator');

        $translator->handleMissingKeysUsing(function (
            string $key,
            array $replace,
            ?string $locale,
            bool $fallback
        ): string {
            $this->handleMissingKey($key, $locale, $fallback);

            return $key;
        });
    }

    /**
     * Handle a missing translation key.
     *
     * @param  string  $key  The missing translation key
     * @param  string|null  $locale  The requested locale
     * @param  bool  $fallback  Whether fallback was attempted
     */
    protected function handleMissingKey(string $key, ?string $locale, bool $fallback): void
    {
        // Skip validation for keys that look like plain text (no namespace or dots)
        // These are likely just using __() for output, not actual translation keys
        if (! str_contains($key, '::') && ! str_contains($key, '.')) {
            return;
        }

        $shouldLog = config('core.lang.log_missing_keys', true);
        $logLevel = config('core.lang.missing_key_log_level', 'debug');

        if (! $shouldLog) {
            return;
        }

        $message = sprintf(
            'Missing translation key: "%s" for locale "%s"%s',
            $key,
            $locale ?? app()->getLocale(),
            $fallback ? ' (fallback enabled)' : ''
        );

        // Log with the configured level
        Log::log($logLevel, $message, [
            'key' => $key,
            'locale' => $locale ?? app()->getLocale(),
            'fallback' => $fallback,
        ]);

        // In local development, also trigger a deprecation notice for visibility
        if ($this->app->environment('local') && function_exists('trigger_deprecation')) {
            trigger_deprecation('core-php', '1.0', $message);
        }
    }
}
