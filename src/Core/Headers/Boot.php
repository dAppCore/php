<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Headers;

use Core\Headers\Livewire\HeaderConfigurationManager;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/**
 * Headers Module Service Provider.
 *
 * Provides HTTP header parsing and security header functionality:
 * - Device detection (User-Agent parsing)
 * - GeoIP lookups (from headers or database)
 * - Configurable security headers (CSP, Permissions-Policy, etc.)
 * - CSP nonce generation for inline scripts/styles
 * - Header configuration UI via Livewire component
 *
 * ## Content Security Policy (CSP) Configuration
 *
 * Configure CSP via `config/headers.php` (published) or environment variables.
 *
 * ### Quick Reference
 *
 * | Option | Environment Variable | Default | Description |
 * |--------|---------------------|---------|-------------|
 * | `csp.enabled` | `SECURITY_CSP_ENABLED` | `true` | Enable/disable CSP entirely |
 * | `csp.report_only` | `SECURITY_CSP_REPORT_ONLY` | `false` | Log violations without blocking |
 * | `csp.report_uri` | `SECURITY_CSP_REPORT_URI` | `null` | URL for violation reports |
 * | `csp.nonce_enabled` | `SECURITY_CSP_NONCE_ENABLED` | `true` | Enable nonce-based CSP |
 * | `csp.nonce_length` | `SECURITY_CSP_NONCE_LENGTH` | `16` | Nonce length in bytes (128 bits) |
 *
 * ### CSP Directives
 *
 * Default directives are configured in `config/headers.php` under `csp.directives`:
 *
 * ```php
 * 'directives' => [
 *     'default-src' => ["'self'"],
 *     'script-src' => ["'self'"],
 *     'style-src' => ["'self'", 'https://fonts.bunny.net'],
 *     'img-src' => ["'self'", 'data:', 'https:', 'blob:'],
 *     'font-src' => ["'self'", 'https://fonts.bunny.net'],
 *     'connect-src' => ["'self'"],
 *     'frame-src' => ["'self'", 'https://www.youtube.com'],
 *     'frame-ancestors' => ["'self'"],
 *     'base-uri' => ["'self'"],
 *     'form-action' => ["'self'"],
 *     'object-src' => ["'none'"],
 * ],
 * ```
 *
 * ### Environment-Specific Overrides
 *
 * Different environments can have different CSP rules:
 *
 * ```php
 * 'environment' => [
 *     'local' => [
 *         'script-src' => ["'unsafe-inline'", "'unsafe-eval'"],
 *         'style-src' => ["'unsafe-inline'"],
 *     ],
 *     'production' => [
 *         // Production should be strict - nonces replace unsafe-inline
 *     ],
 * ],
 * ```
 *
 * ### Nonce-Based CSP
 *
 * Nonces provide secure inline script/style support without `'unsafe-inline'`.
 *
 * #### In Blade Templates
 *
 * ```blade
 * {{-- Using the helper function --}}
 * <script nonce="{{ csp_nonce() }}">
 *     console.log('Allowed by nonce');
 * </script>
 *
 * {{-- Using the Blade directive --}}
 * <script @cspnonce>
 *     console.log('Also allowed');
 * </script>
 *
 * {{-- Just the nonce value --}}
 * <script nonce="@cspnoncevalue">
 *     console.log('Works too');
 * </script>
 * ```
 *
 * #### Nonce Skip Environments
 *
 * In local/development environments, nonces are skipped by default to allow
 * hot reload and dev tools. Configure via `csp.nonce_skip_environments`:
 *
 * ```php
 * 'nonce_skip_environments' => ['local', 'development'],
 * ```
 *
 * ### External Service Sources
 *
 * Enable third-party services via environment variables:
 *
 * | Service | Environment Variable | Sources Added |
 * |---------|---------------------|---------------|
 * | jsDelivr | `SECURITY_CSP_JSDELIVR` | cdn.jsdelivr.net |
 * | unpkg | `SECURITY_CSP_UNPKG` | unpkg.com |
 * | Google Analytics | `SECURITY_CSP_GOOGLE_ANALYTICS` | googletagmanager.com, google-analytics.com |
 * | Facebook | `SECURITY_CSP_FACEBOOK` | connect.facebook.net, facebook.com |
 *
 * ### Other Security Headers
 *
 * | Header | Option | Default |
 * |--------|--------|---------|
 * | Strict-Transport-Security | `hsts.enabled` | `true` (production only) |
 * | X-Frame-Options | `x_frame_options` | `SAMEORIGIN` |
 * | X-Content-Type-Options | `x_content_type_options` | `nosniff` |
 * | X-XSS-Protection | `x_xss_protection` | `1; mode=block` |
 * | Referrer-Policy | `referrer_policy` | `strict-origin-when-cross-origin` |
 * | Permissions-Policy | `permissions.enabled` | `true` |
 *
 * @see SecurityHeaders For the middleware implementation
 * @see CspNonceService For nonce generation
 * @see config/headers.php For full configuration reference
 */
class Boot extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config.php', 'headers');

        $this->app->singleton(DetectDevice::class);
        $this->app->singleton(DetectLocation::class);

        // Register CSP nonce service as singleton (one nonce per request)
        $this->app->singleton(CspNonceService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/Views', 'core');

        $this->registerBladeDirectives();
        $this->registerHelperFunctions();
        $this->registerLivewireComponents();
    }

    /**
     * Register Blade directives for CSP nonces.
     */
    protected function registerBladeDirectives(): void
    {
        // @cspnonce - Outputs the nonce attribute
        // Usage: <script @cspnonce>...</script>
        Blade::directive('cspnonce', function () {
            return '<?php echo app(\Core\Headers\CspNonceService::class)->getNonceAttribute(); ?>';
        });

        // @cspnoncevalue - Outputs just the nonce value (for use in nonce="...")
        // Usage: <script nonce="@cspnoncevalue">...</script>
        Blade::directive('cspnoncevalue', function () {
            return '<?php echo app(\Core\Headers\CspNonceService::class)->getNonce(); ?>';
        });
    }

    /**
     * Register global helper functions.
     */
    protected function registerHelperFunctions(): void
    {
        // Register the csp_nonce() helper function
        if (! function_exists('csp_nonce')) {
            require __DIR__.'/helpers.php';
        }
    }

    /**
     * Register Livewire components.
     */
    protected function registerLivewireComponents(): void
    {
        // Only register if Livewire is available
        if (class_exists(Livewire::class)) {
            Livewire::component('header-configuration-manager', HeaderConfigurationManager::class);
        }
    }
}
