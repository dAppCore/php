<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Headers;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Add security headers to all responses.
 *
 * Headers added:
 * - Strict-Transport-Security (HSTS) - enforce HTTPS
 * - Content-Security-Policy - restrict resource loading
 * - X-Content-Type-Options - prevent MIME sniffing
 * - X-Frame-Options - prevent clickjacking
 * - X-XSS-Protection - enable browser XSS filtering
 * - Referrer-Policy - control referrer information
 * - Permissions-Policy - control browser features
 *
 * Supports nonce-based CSP for inline scripts and styles via CspNonceService.
 *
 * ## Usage
 *
 * Register in your HTTP kernel or route middleware:
 *
 * ```php
 * // app/Http/Kernel.php
 * protected $middleware = [
 *     // ...
 *     \Core\Headers\SecurityHeaders::class,
 * ];
 * ```
 *
 * ## CSP Directive Resolution
 *
 * CSP directives are built in this order:
 * 1. Base directives from `config('headers.csp.directives')`
 * 2. Environment-specific overrides from `config('headers.csp.environment')`
 * 3. Nonces added to script-src/style-src (if enabled)
 * 4. CDN sources from `config('core.cdn.subdomain')`
 * 5. External service sources (jsDelivr, Google Analytics, etc.)
 * 6. Development WebSocket sources (localhost:8080)
 * 7. Report URI (if configured)
 *
 * ## Report-Only Mode
 *
 * Enable `SECURITY_CSP_REPORT_ONLY=true` to log violations without blocking.
 * This uses the `Content-Security-Policy-Report-Only` header instead.
 *
 * ## HSTS Behaviour
 *
 * HSTS is only added in production environments to avoid issues with
 * local development over HTTP. Configure via:
 *
 * - `SECURITY_HSTS_ENABLED` - Enable/disable HSTS
 * - `SECURITY_HSTS_MAX_AGE` - Max age in seconds (default: 1 year)
 * - `SECURITY_HSTS_INCLUDE_SUBDOMAINS` - Include subdomains
 * - `SECURITY_HSTS_PRELOAD` - Enable preload flag for browser preload lists
 *
 * @see CspNonceService For nonce generation
 * @see Boot For configuration documentation
 */
class SecurityHeaders
{
    /**
     * The CSP nonce service.
     */
    protected ?CspNonceService $nonceService = null;

    public function __construct(?CspNonceService $nonceService = null)
    {
        $this->nonceService = $nonceService ?? App::make(CspNonceService::class);
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! config('headers.enabled', true)) {
            return $response;
        }

        $this->addHstsHeader($response);
        $this->addCspHeader($response);
        $this->addPermissionsPolicyHeader($response);
        $this->addStandardSecurityHeaders($response);

        return $response;
    }

    /**
     * Get the CSP nonce service.
     */
    public function getNonceService(): CspNonceService
    {
        return $this->nonceService;
    }

    /**
     * Add Strict-Transport-Security header.
     */
    protected function addHstsHeader(Response $response): void
    {
        $config = config('headers.hsts', []);

        if (! ($config['enabled'] ?? true)) {
            return;
        }

        // Only add HSTS in production to avoid issues with local development
        if (! app()->environment('production')) {
            return;
        }

        $maxAge = $config['max_age'] ?? 31536000;
        $value = "max-age={$maxAge}";

        if ($config['include_subdomains'] ?? true) {
            $value .= '; includeSubDomains';
        }

        if ($config['preload'] ?? true) {
            $value .= '; preload';
        }

        $response->headers->set('Strict-Transport-Security', $value);
    }

    /**
     * Add Content-Security-Policy header.
     */
    protected function addCspHeader(Response $response): void
    {
        $config = config('headers.csp', []);

        if (! ($config['enabled'] ?? true)) {
            return;
        }

        $directives = $this->buildCspDirectives($config);
        $cspValue = $this->formatCspDirectives($directives);

        $headerName = ($config['report_only'] ?? false)
            ? 'Content-Security-Policy-Report-Only'
            : 'Content-Security-Policy';

        $response->headers->set($headerName, $cspValue);
    }

    /**
     * Build CSP directives from configuration.
     *
     * @return array<string, array<string>>
     */
    protected function buildCspDirectives(array $config): array
    {
        $directives = $config['directives'] ?? $this->getDefaultCspDirectives();

        // Apply environment-specific overrides
        $directives = $this->applyEnvironmentOverrides($directives, $config);

        // Add nonces for script-src and style-src if enabled
        $directives = $this->addNonceDirectives($directives, $config);

        // Add CDN subdomain sources
        $directives = $this->addCdnSources($directives, $config);

        // Add external service sources
        $directives = $this->addExternalSources($directives, $config);

        // Add WebSocket sources for development
        $directives = $this->addDevelopmentWebsocketSources($directives);

        // Add report-uri if configured
        if (! empty($config['report_uri'])) {
            $directives['report-uri'] = [$config['report_uri']];
        }

        return $directives;
    }

    /**
     * Add nonce directives for script-src and style-src.
     *
     * When nonce-based CSP is enabled, nonces are added to script-src and
     * style-src directives, allowing inline scripts/styles that include
     * the matching nonce attribute.
     *
     * @return array<string, array<string>>
     */
    protected function addNonceDirectives(array $directives, array $config): array
    {
        $nonceEnabled = $config['nonce_enabled'] ?? true;

        // Skip if nonces are disabled
        if (! $nonceEnabled || ! $this->nonceService?->isEnabled()) {
            return $directives;
        }

        // Don't add nonces in local/development environments with unsafe-inline
        // as it would be redundant and could cause issues
        $environment = app()->environment();
        $skipNonceEnvs = $config['nonce_skip_environments'] ?? ['local', 'development'];

        if (in_array($environment, $skipNonceEnvs, true)) {
            return $directives;
        }

        $nonce = $this->nonceService->getCspNonceDirective();
        $nonceDirectives = $config['nonce_directives'] ?? ['script-src', 'style-src'];

        foreach ($nonceDirectives as $directive) {
            if (isset($directives[$directive])) {
                // Remove unsafe-inline if present and add nonce
                // Nonces are more secure than unsafe-inline
                $directives[$directive] = array_filter(
                    $directives[$directive],
                    fn ($value) => $value !== "'unsafe-inline'"
                );
                $directives[$directive][] = $nonce;
            }
        }

        return $directives;
    }

    /**
     * Get default CSP directives.
     *
     * @return array<string, array<string>>
     */
    protected function getDefaultCspDirectives(): array
    {
        return [
            'default-src' => ["'self'"],
            'script-src' => ["'self'"],
            'style-src' => ["'self'", 'https://fonts.bunny.net', 'https://fonts.googleapis.com'],
            'img-src' => ["'self'", 'data:', 'https:', 'blob:'],
            'font-src' => ["'self'", 'https://fonts.bunny.net', 'https://fonts.gstatic.com', 'data:'],
            'connect-src' => ["'self'"],
            'frame-src' => ["'self'", 'https://www.youtube.com', 'https://player.vimeo.com'],
            'frame-ancestors' => ["'self'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'object-src' => ["'none'"],
        ];
    }

    /**
     * Apply environment-specific CSP overrides.
     *
     * @return array<string, array<string>>
     */
    protected function applyEnvironmentOverrides(array $directives, array $config): array
    {
        $environment = app()->environment();
        $envOverrides = $config['environment'][$environment] ?? [];

        foreach ($envOverrides as $directive => $sources) {
            if (isset($directives[$directive])) {
                $directives[$directive] = array_unique(array_merge($directives[$directive], $sources));
            } else {
                $directives[$directive] = $sources;
            }
        }

        return $directives;
    }

    /**
     * Add CDN subdomain to relevant directives.
     *
     * @return array<string, array<string>>
     */
    protected function addCdnSources(array $directives, array $config): array
    {
        $baseDomain = config('core.domain.base', 'core.test');
        $cdnSubdomain = config('core.cdn.subdomain', 'cdn');
        $cdnUrl = "https://{$cdnSubdomain}.{$baseDomain}";

        $cdnConfig = $config['external']['cdn'] ?? [];

        foreach ($cdnConfig as $directive => $enabled) {
            if ($enabled && isset($directives[$directive])) {
                $directives[$directive][] = $cdnUrl;
            }
        }

        // Add base domain for connect-src (WebSocket, API calls)
        if (isset($directives['connect-src'])) {
            $directives['connect-src'][] = "https://*.{$baseDomain}";
            $directives['connect-src'][] = "wss://*.{$baseDomain}";
            $directives['connect-src'][] = "wss://{$baseDomain}:8080";
        }

        return $directives;
    }

    /**
     * Add external service sources based on configuration.
     *
     * @return array<string, array<string>>
     */
    protected function addExternalSources(array $directives, array $config): array
    {
        $external = $config['external'] ?? [];

        foreach ($external as $service => $serviceConfig) {
            // Skip CDN (handled separately) and disabled services
            if ($service === 'cdn') {
                continue;
            }

            if (! ($serviceConfig['enabled'] ?? false)) {
                continue;
            }

            foreach ($serviceConfig as $directive => $sources) {
                if ($directive === 'enabled' || ! is_array($sources)) {
                    continue;
                }

                if (isset($directives[$directive])) {
                    $directives[$directive] = array_merge($directives[$directive], $sources);
                }
            }
        }

        return $directives;
    }

    /**
     * Add WebSocket sources for development environments.
     *
     * @return array<string, array<string>>
     */
    protected function addDevelopmentWebsocketSources(array $directives): array
    {
        if (app()->environment('production')) {
            return $directives;
        }

        if (isset($directives['connect-src'])) {
            $directives['connect-src'] = array_merge($directives['connect-src'], [
                'wss://localhost:8080',
                'ws://localhost:8080',
                'wss://127.0.0.1:8080',
                'ws://127.0.0.1:8080',
            ]);
        }

        return $directives;
    }

    /**
     * Format CSP directives into header value.
     */
    protected function formatCspDirectives(array $directives): string
    {
        $parts = [];

        foreach ($directives as $directive => $sources) {
            $uniqueSources = array_unique($sources);
            $parts[] = $directive.' '.implode(' ', $uniqueSources);
        }

        return implode('; ', $parts);
    }

    /**
     * Add Permissions-Policy header.
     */
    protected function addPermissionsPolicyHeader(Response $response): void
    {
        $config = config('headers.permissions', []);

        if (! ($config['enabled'] ?? true)) {
            return;
        }

        $features = $config['features'] ?? $this->getDefaultPermissionsPolicy();
        $parts = [];

        foreach ($features as $feature => $allowList) {
            if (empty($allowList)) {
                $parts[] = "{$feature}=()";
            } else {
                $formatted = array_map(fn ($origin) => $origin === 'self' ? 'self' : "\"{$origin}\"", $allowList);
                $parts[] = "{$feature}=(".implode(' ', $formatted).')';
            }
        }

        $response->headers->set('Permissions-Policy', implode(', ', $parts));
    }

    /**
     * Get default Permissions-Policy features.
     *
     * @return array<string, array<string>>
     */
    protected function getDefaultPermissionsPolicy(): array
    {
        return [
            'accelerometer' => [],
            'autoplay' => ['self'],
            'camera' => [],
            'encrypted-media' => ['self'],
            'fullscreen' => ['self'],
            'geolocation' => [],
            'gyroscope' => [],
            'magnetometer' => [],
            'microphone' => [],
            'payment' => [],
            'picture-in-picture' => ['self'],
            'sync-xhr' => ['self'],
            'usb' => [],
        ];
    }

    /**
     * Add standard security headers.
     */
    protected function addStandardSecurityHeaders(Response $response): void
    {
        $response->headers->set(
            'X-Content-Type-Options',
            config('headers.x_content_type_options', 'nosniff')
        );

        $response->headers->set(
            'X-Frame-Options',
            config('headers.x_frame_options', 'SAMEORIGIN')
        );

        $response->headers->set(
            'X-XSS-Protection',
            config('headers.x_xss_protection', '1; mode=block')
        );

        $response->headers->set(
            'Referrer-Policy',
            config('headers.referrer_policy', 'strict-origin-when-cross-origin')
        );
    }
}
