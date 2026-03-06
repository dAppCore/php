<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

/**
 * Core PHP Framework Configuration.
 *
 * This is the main configuration for the Core PHP modular monolith framework.
 * All branding, domains, and service settings are configurable here.
 *
 * For local development with Laravel Valet:
 *   valet link core
 *   => core.test (with wildcard: cdn.core.test, api.core.test, etc.)
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Application Branding
    |--------------------------------------------------------------------------
    |
    | Your application's name, tagline, and branding assets. These are used
    | throughout the UI, emails, SEO metadata, and error pages.
    |
    */
    'app' => [
        'name' => env('APP_BRAND_NAME', 'Core PHP'),
        'tagline' => env('APP_TAGLINE', 'Modular Monolith Framework'),
        'description' => env('APP_DESCRIPTION'),
        'logo' => env('APP_LOGO', '/images/logo.svg'),
        'icon' => env('APP_ICON', '/images/icon.svg'),
        'favicon' => env('APP_FAVICON', '/favicon.ico'),
        'copyright' => env('APP_COPYRIGHT', 'Core PHP Framework'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Domain Configuration
    |--------------------------------------------------------------------------
    |
    | The base domain and TLD for your application. With Valet, this defaults
    | to .test TLD. In production, set your actual domain.
    |
    | Examples:
    |   Local (Valet): core.test, cdn.core.test
    |   Production: myapp.com, cdn.myapp.com
    |
    */
    'domain' => [
        // Base domain without protocol (e.g., 'core.test' or 'myapp.com')
        'base' => env('APP_DOMAIN', 'core.test'),

        // TLD for local development
        'tld' => env('APP_TLD', '.test'),

        // Domains to exclude from workspace resolution (always serve main app)
        'excluded' => array_filter(array_map('trim', explode(',', env('DOMAIN_EXCLUDED', '')))),
    ],

    /*
    |--------------------------------------------------------------------------
    | CDN / Asset Delivery
    |--------------------------------------------------------------------------
    |
    | Configure how static assets are served. By default, assets are served
    | locally with aggressive cache headers. Optional external CDN support.
    |
    | Local mode (default): Assets served from /public with cache headers
    | CDN mode: Assets served from external CDN (BunnyCDN, CloudFlare, etc.)
    |
    */
    'cdn' => [
        // Enable external CDN (false = serve locally)
        'enabled' => env('CDN_ENABLED', false),

        // CDN URL when enabled (e.g., 'https://cdn.myapp.com')
        'url' => env('CDN_URL'),

        // Local subdomain for CDN-like behaviour with Valet (cdn.core.test)
        'subdomain' => env('CDN_SUBDOMAIN', 'cdn'),

        // Cache max-age for static assets (1 year default for versioned assets)
        'cache_max_age' => env('CDN_CACHE_MAX_AGE', 31536000),

        // Immutable cache for versioned assets
        'cache_immutable' => env('CDN_CACHE_IMMUTABLE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Organisation / Legal
    |--------------------------------------------------------------------------
    |
    | Used in schema.org markup, legal pages, and contact information.
    |
    */
    'organisation' => [
        'name' => env('ORG_NAME', env('APP_BRAND_NAME', 'Core PHP')),
        'legal_name' => env('ORG_LEGAL_NAME'),
        'url' => env('ORG_URL', env('APP_URL', 'https://core.test')),
        'logo' => env('ORG_LOGO'),
        'email' => env('ORG_EMAIL'),
        'phone' => env('ORG_PHONE'),
        'address' => [
            'street' => env('ORG_ADDRESS_STREET'),
            'city' => env('ORG_ADDRESS_CITY'),
            'region' => env('ORG_ADDRESS_REGION'),
            'postal_code' => env('ORG_ADDRESS_POSTAL'),
            'country' => env('ORG_ADDRESS_COUNTRY'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Social Links
    |--------------------------------------------------------------------------
    |
    | Social media profiles for schema.org and footer links.
    |
    */
    'social' => [
        'twitter' => env('SOCIAL_TWITTER'),
        'facebook' => env('SOCIAL_FACEBOOK'),
        'instagram' => env('SOCIAL_INSTAGRAM'),
        'linkedin' => env('SOCIAL_LINKEDIN'),
        'github' => env('SOCIAL_GITHUB'),
        'youtube' => env('SOCIAL_YOUTUBE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Contact Information
    |--------------------------------------------------------------------------
    |
    | Contact details displayed in footer, error pages, and support areas.
    |
    */
    'contact' => [
        'email' => env('CONTACT_EMAIL'),
        'support_email' => env('SUPPORT_EMAIL'),
        'phone' => env('CONTACT_PHONE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | External Services URLs
    |--------------------------------------------------------------------------
    |
    | URLs for external services (status page, docs, etc.)
    |
    */
    'urls' => [
        'status' => env('URL_STATUS'),
        'docs' => env('URL_DOCS'),
        'support' => env('URL_SUPPORT'),
        'privacy' => env('URL_PRIVACY', '/privacy'),
        'terms' => env('URL_TERMS', '/terms'),
    ],

    /*
    |--------------------------------------------------------------------------
    | FontAwesome Configuration
    |--------------------------------------------------------------------------
    |
    | Set 'pro' to true if you have FontAwesome Pro. This enables additional
    | icon styles: light, thin, duotone, sharp, and jelly.
    |
    | Free version supports: solid, regular, brands
    |
    */
    'fontawesome' => [
        'pro' => env('FONTAWESOME_PRO', false),
        'kit' => env('FONTAWESOME_KIT'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pro Component Fallback Behaviour
    |--------------------------------------------------------------------------
    |
    | How to handle Flux Pro components when Flux Pro isn't installed.
    |
    | Options:
    |   'error'    - Show a helpful error message (recommended for development)
    |   'fallback' - Render a basic HTML fallback where possible
    |   'silent'   - Render nothing (for production, use with caution)
    |
    */
    'pro_fallback' => env('CORE_PRO_FALLBACK', 'error'),

    /*
    |--------------------------------------------------------------------------
    | Icon Defaults
    |--------------------------------------------------------------------------
    |
    | Default icon style when not specified. Only applies when not using
    | auto-detection (brand/jelly lists).
    |
    */
    'icon' => [
        'default_style' => 'solid',
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug / Developer Mode
    |--------------------------------------------------------------------------
    |
    | Settings for developer/admin access to debug information.
    |
    */
    'debug' => [
        // Enable encrypted stack traces in production error pages
        'encrypted_traces' => env('DEBUG_ENCRYPTED_TRACES', true),

        // Cookie name for developer access
        'cookie' => env('DEBUG_COOKIE', 'core_debug'),

        // Token for debug access (set in .env for production)
        'token' => env('DEBUG_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Seeder Auto-Discovery
    |--------------------------------------------------------------------------
    |
    | Configure how database seeders are discovered and ordered.
    |
    | The seeder system automatically finds *Seeder.php files in module
    | Database/Seeders directories and orders them based on priority
    | and dependency declarations.
    |
    | Priority Guidelines:
    |   0-20: Foundation seeders (features, configuration)
    |   20-40: Core data (packages, workspaces)
    |   40-60: Default priority (general seeders)
    |   60-80: Content seeders (pages, posts)
    |   80-100: Demo/test data seeders
    |
    */
    'seeders' => [
        // Enable auto-discovery of seeders from module paths
        'auto_discover' => env('SEEDER_AUTO_DISCOVER', true),

        // Paths to scan for module seeders (relative to app_path())
        // Each path is scanned for */Database/Seeders/*Seeder.php files
        'paths' => [
            // Populated dynamically from app_path() in CoreDatabaseSeeder
            // Override here to specify absolute paths
        ],

        // Seeder classes to exclude (fully qualified class names)
        // Example: [Core\Tenant\Database\Seeders\DemoWorkspaceSeeder::class]
        'exclude' => [],
    ],
];
