<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

/**
 * CDN / Storage Configuration.
 *
 * By default, assets are served locally with aggressive cache headers.
 * For production, you can enable external CDN (BunnyCDN, CloudFlare, etc.)
 *
 * Local Development (Valet):
 *   Assets served from /public with proper cache headers
 *   Optional: cdn.{app}.test subdomain for CDN simulation
 *
 * Production:
 *   Enable CDN and configure your provider below
 */

return [
    /*
    |--------------------------------------------------------------------------
    | CDN Mode
    |--------------------------------------------------------------------------
    |
    | When disabled, assets are served locally from /public with cache headers.
    | Enable for production with an external CDN.
    |
    */
    'enabled' => env('CDN_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Signed URL Expiry
    |--------------------------------------------------------------------------
    |
    | Default expiry time (in seconds) for signed URLs when not specified
    | per-request. Signed URLs provide time-limited access to private content.
    |
    */
    'signed_url_expiry' => env('CDN_SIGNED_URL_EXPIRY', 3600),

    /*
    |--------------------------------------------------------------------------
    | URL Configuration
    |--------------------------------------------------------------------------
    |
    | All URL building uses these config values for consistency.
    | Never hardcode URLs in service methods.
    |
    */
    'urls' => [
        // CDN delivery URL (when enabled)
        'cdn' => env('CDN_URL'),

        // Public origin URL (direct storage access, bypassing CDN)
        'public' => env('CDN_PUBLIC_URL'),

        // Private CDN URL (for signed/gated content)
        'private' => env('CDN_PRIVATE_URL'),

        // Apex domain fallback
        'apex' => env('APP_URL', 'https://core.test'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disk Mapping
    |--------------------------------------------------------------------------
    */
    'disks' => [
        'private' => env('CDN_PRIVATE_DISK', 'local'),
        'public' => env('CDN_PUBLIC_DISK', 'public'),
    ],

    /*
    |--------------------------------------------------------------------------
    | BunnyCDN Configuration (Optional)
    |--------------------------------------------------------------------------
    |
    | Only needed if using BunnyCDN as your CDN provider.
    |
    */
    'bunny' => [
        // Public storage zone (compiled assets)
        'public' => [
            'zone' => env('BUNNYCDN_PUBLIC_STORAGE_ZONE'),
            'region' => env('BUNNYCDN_PUBLIC_STORAGE_REGION', 'de'),
            'api_key' => env('BUNNYCDN_PUBLIC_STORAGE_API_KEY'),
            'read_only_key' => env('BUNNYCDN_PUBLIC_STORAGE_READ_KEY'),
            'pull_zone' => env('BUNNYCDN_PUBLIC_PULL_ZONE'),
        ],

        // Private storage zone (DRM, gated content)
        'private' => [
            'zone' => env('BUNNYCDN_PRIVATE_STORAGE_ZONE'),
            'region' => env('BUNNYCDN_PRIVATE_STORAGE_REGION', 'de'),
            'api_key' => env('BUNNYCDN_PRIVATE_STORAGE_API_KEY'),
            'read_only_key' => env('BUNNYCDN_PRIVATE_STORAGE_READ_KEY'),
            'pull_zone' => env('BUNNYCDN_PRIVATE_PULL_ZONE'),
            'token' => env('BUNNYCDN_PRIVATE_PULL_ZONE_TOKEN'),
        ],

        // Account-level API (for cache purging)
        'pull_zone_id' => env('BUNNYCDN_PULL_ZONE_ID'),
        'api_key' => env('BUNNYCDN_API_KEY'),

        // Feature flags
        'push_enabled' => env('CDN_PUSH_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Context Detection
    |--------------------------------------------------------------------------
    |
    | Define which routes/contexts should use origin vs CDN URLs.
    |
    */
    'context' => [
        // Route prefixes that should use origin URLs (admin/internal)
        'admin_prefixes' => ['admin', 'hub', 'api/v1/admin', 'dashboard'],

        // Headers that indicate internal/admin request
        'admin_headers' => ['X-Admin-Request', 'X-Internal-Request'],

        // Default context when not determinable
        'default' => 'public',
    ],

    /*
    |--------------------------------------------------------------------------
    | Asset Processing Pipeline
    |--------------------------------------------------------------------------
    */
    'pipeline' => [
        // Auto-push to CDN after processing (only when CDN enabled)
        'auto_push' => env('CDN_AUTO_PUSH', false),

        // Auto-purge CDN on asset update
        'auto_purge' => env('CDN_AUTO_PURGE', false),

        // Queue for async operations
        'queue' => env('CDN_QUEUE', 'default'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Path Prefixes by Category
    |--------------------------------------------------------------------------
    */
    'paths' => [
        'media' => 'media',
        'avatar' => 'avatars',
        'content' => 'content',
        'static' => 'static',
    ],

    /*
    |--------------------------------------------------------------------------
    | Local Cache Configuration
    |--------------------------------------------------------------------------
    |
    | When CDN is disabled, these settings control local asset caching.
    |
    */
    'cache' => [
        'enabled' => env('CDN_CACHE_ENABLED', true),
        'ttl' => env('CDN_CACHE_TTL', 3600),
        'prefix' => 'cdn_url',

        // Cache headers for static assets (when serving locally)
        'headers' => [
            'max_age' => env('CDN_CACHE_MAX_AGE', 31536000), // 1 year
            'immutable' => env('CDN_CACHE_IMMUTABLE', true),
        ],
    ],
];
