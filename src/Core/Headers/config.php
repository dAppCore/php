<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

/**
 * Security Headers Configuration.
 *
 * Configure Content-Security-Policy, Permissions-Policy, and other
 * security headers. Environment-specific overrides are supported.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Enable Security Headers
    |--------------------------------------------------------------------------
    |
    | Master switch to enable/disable all security headers.
    |
    */

    'enabled' => env('SECURITY_HEADERS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Strict Transport Security (HSTS)
    |--------------------------------------------------------------------------
    |
    | HSTS enforces HTTPS connections. Only enabled in production by default.
    |
    */

    'hsts' => [
        'enabled' => env('SECURITY_HSTS_ENABLED', true),
        'max_age' => env('SECURITY_HSTS_MAX_AGE', 31536000), // 1 year
        'include_subdomains' => env('SECURITY_HSTS_INCLUDE_SUBDOMAINS', true),
        'preload' => env('SECURITY_HSTS_PRELOAD', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Content Security Policy (CSP)
    |--------------------------------------------------------------------------
    |
    | CSP controls which resources can be loaded. Configure directives below.
    | Set 'enabled' to false to disable CSP entirely.
    |
    | IMPORTANT: Avoid 'unsafe-inline' and 'unsafe-eval' in production.
    | Use nonces or hashes for inline scripts/styles instead.
    |
    */

    'csp' => [
        'enabled' => env('SECURITY_CSP_ENABLED', true),

        // Report-Only mode (logs violations without blocking)
        'report_only' => env('SECURITY_CSP_REPORT_ONLY', false),

        // Report URI for CSP violation reports
        'report_uri' => env('SECURITY_CSP_REPORT_URI'),

        /*
        |----------------------------------------------------------------------
        | Nonce-based CSP
        |----------------------------------------------------------------------
        |
        | When enabled, a unique nonce is generated per request and added to
        | script-src and style-src directives. Inline scripts/styles must
        | include the nonce attribute to be allowed.
        |
        | Usage in Blade:
        |   <script nonce="{{ csp_nonce() }}">...</script>
        |   <script @cspnonce>...</script>
        |
        */

        // Enable nonce-based CSP (recommended for production)
        'nonce_enabled' => env('SECURITY_CSP_NONCE_ENABLED', true),

        // Nonce length in bytes (16 = 128 bits, recommended minimum)
        'nonce_length' => env('SECURITY_CSP_NONCE_LENGTH', 16),

        // Directives to add nonces to
        'nonce_directives' => ['script-src', 'style-src'],

        // Environments where nonces are skipped (unsafe-inline is used instead)
        // This avoids issues with hot reload and dev tools
        'nonce_skip_environments' => ['local', 'development'],

        // CSP Directives
        'directives' => [
            'default-src' => ["'self'"],

            // Script sources - avoid unsafe-inline/eval in production
            'script-src' => [
                "'self'",
                // Add 'unsafe-inline' only for development/legacy support
                // Production should use nonces instead
            ],

            // Style sources
            'style-src' => [
                "'self'",
                'https://fonts.bunny.net',
                'https://fonts.googleapis.com',
            ],

            // Image sources
            'img-src' => [
                "'self'",
                'data:',
                'https:',
                'blob:',
            ],

            // Font sources
            'font-src' => [
                "'self'",
                'https://fonts.bunny.net',
                'https://fonts.gstatic.com',
                'data:',
            ],

            // Connect sources (XHR, WebSocket, etc.)
            'connect-src' => [
                "'self'",
            ],

            // Frame sources (iframes)
            'frame-src' => [
                "'self'",
                'https://www.youtube.com',
                'https://player.vimeo.com',
            ],

            // Frame ancestors (who can embed this page)
            'frame-ancestors' => ["'self'"],

            // Base URI restriction
            'base-uri' => ["'self'"],

            // Form action restriction
            'form-action' => ["'self'"],

            // Object sources (plugins, etc.)
            'object-src' => ["'none'"],
        ],

        // Additional sources per environment
        // These are merged with the base directives
        'environment' => [
            'local' => [
                'script-src' => ["'unsafe-inline'", "'unsafe-eval'"],
                'style-src' => ["'unsafe-inline'"],
            ],
            'development' => [
                'script-src' => ["'unsafe-inline'", "'unsafe-eval'"],
                'style-src' => ["'unsafe-inline'"],
            ],
            'staging' => [
                'script-src' => ["'unsafe-inline'"],
                'style-src' => ["'unsafe-inline'"],
            ],
            'production' => [
                // Production should be strict - no unsafe-inline
                // Add nonce support or specific hashes as needed
            ],
        ],

        // Additional external sources (CDN, analytics, etc.)
        // These are added to the appropriate directives based on config
        'external' => [
            // CDN subdomain (auto-populated from core.cdn.subdomain)
            'cdn' => [
                'script-src' => true,
                'style-src' => true,
                'font-src' => true,
                'img-src' => true,
            ],

            // Third-party services - enable as needed
            'jsdelivr' => [
                'enabled' => env('SECURITY_CSP_JSDELIVR', false),
                'script-src' => ['https://cdn.jsdelivr.net'],
                'style-src' => ['https://cdn.jsdelivr.net'],
            ],

            'unpkg' => [
                'enabled' => env('SECURITY_CSP_UNPKG', false),
                'script-src' => ['https://unpkg.com'],
                'style-src' => ['https://unpkg.com'],
            ],

            'google_analytics' => [
                'enabled' => env('SECURITY_CSP_GOOGLE_ANALYTICS', false),
                'script-src' => ['https://www.googletagmanager.com', 'https://www.google-analytics.com'],
                'connect-src' => ['https://www.google-analytics.com'],
                'img-src' => ['https://www.google-analytics.com'],
            ],

            'facebook' => [
                'enabled' => env('SECURITY_CSP_FACEBOOK', false),
                'script-src' => ['https://connect.facebook.net'],
                'frame-src' => ['https://www.facebook.com'],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Permissions Policy (formerly Feature-Policy)
    |--------------------------------------------------------------------------
    |
    | Controls browser features like camera, microphone, geolocation, etc.
    | Default is restrictive - enable features as needed.
    |
    */

    'permissions' => [
        'enabled' => env('SECURITY_PERMISSIONS_ENABLED', true),

        // Feature permissions - empty () means disabled, (self) allows same-origin
        'features' => [
            'accelerometer' => [],
            'autoplay' => ['self'],
            'camera' => [],
            'cross-origin-isolated' => [],
            'display-capture' => [],
            'encrypted-media' => ['self'],
            'fullscreen' => ['self'],
            'geolocation' => [],
            'gyroscope' => [],
            'keyboard-map' => [],
            'magnetometer' => [],
            'microphone' => [],
            'midi' => [],
            'payment' => [],
            'picture-in-picture' => ['self'],
            'publickey-credentials-get' => [],
            'screen-wake-lock' => [],
            'sync-xhr' => ['self'],
            'usb' => [],
            'web-share' => ['self'],
            'xr-spatial-tracking' => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Other Security Headers
    |--------------------------------------------------------------------------
    */

    'x_frame_options' => env('SECURITY_X_FRAME_OPTIONS', 'SAMEORIGIN'),
    'x_content_type_options' => 'nosniff',
    'x_xss_protection' => '1; mode=block',
    'referrer_policy' => env('SECURITY_REFERRER_POLICY', 'strict-origin-when-cross-origin'),
];
