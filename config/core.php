<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Branding
    |--------------------------------------------------------------------------
    |
    | These settings control the public-facing website branding.
    | Override these in your application's config/core.php to customise.
    |
    */

    'app' => [
        'name' => env('APP_NAME', 'Core PHP'),
        'description' => env('APP_DESCRIPTION', 'A modular monolith framework'),
        'tagline' => env('APP_TAGLINE', 'Build powerful applications with a clean, modular architecture.'),
        'cta_text' => env('APP_CTA_TEXT', 'Join developers building with our framework.'),
        'icon' => env('APP_ICON', 'cube'),
        'color' => env('APP_COLOR', 'violet'),
        'logo' => env('APP_LOGO'),  // Path relative to public/, e.g. 'images/logo.svg'
        'privacy_url' => env('APP_PRIVACY_URL'),
        'terms_url' => env('APP_TERMS_URL'),
        'powered_by' => env('APP_POWERED_BY'),
        'powered_by_url' => env('APP_POWERED_BY_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Module Paths
    |--------------------------------------------------------------------------
    |
    | Directories to scan for module Boot.php files with $listens declarations.
    | Each path should be an absolute path to a directory containing modules.
    |
    | Example:
    |     'module_paths' => [
    |         app_path('Core'),
    |         app_path('Mod'),
    |     ],
    |
    */

    'module_paths' => [
        // app_path('Core'),
        // app_path('Mod'),
    ],

    /*
    |--------------------------------------------------------------------------
    | FontAwesome Configuration
    |--------------------------------------------------------------------------
    |
    | Configure FontAwesome Pro detection and fallback behaviour.
    |
    */

    'fontawesome' => [
        // Set to true if you have a FontAwesome Pro licence
        'pro' => env('FONTAWESOME_PRO', false),

        // Your FontAwesome Kit ID (optional)
        'kit' => env('FONTAWESOME_KIT'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pro Fallback Behaviour
    |--------------------------------------------------------------------------
    |
    | How to handle Pro-only components when Pro packages aren't installed.
    |
    | Options:
    |   - 'error': Throw exception in dev, silent in production
    |   - 'fallback': Use free alternatives where possible
    |   - 'silent': Render nothing for Pro-only components
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
    | Search Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the unified search feature including searchable API endpoints.
    | Add your application's API endpoints here to include them in search results.
    |
    */

    'search' => [
        'api_endpoints' => [
            // Example endpoints - override in your application's config
            // ['method' => 'GET', 'path' => '/api/v1/users', 'description' => 'List users'],
            // ['method' => 'POST', 'path' => '/api/v1/users', 'description' => 'Create user'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Shield Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the Email Shield validation and statistics module.
    | Statistics track daily email validation counts for monitoring and
    | analysis. Old records are automatically pruned based on retention period.
    |
    | Schedule the prune command in your app/Console/Kernel.php:
    |     $schedule->command('email-shield:prune')->daily();
    |
    */

    'email_shield' => [
        // Number of days to retain email shield statistics records.
        // Records older than this will be deleted by the prune command.
        // Set to 0 to disable automatic pruning.
        'retention_days' => env('CORE_EMAIL_SHIELD_RETENTION_DAYS', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | Admin Menu Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the admin menu caching behaviour. Menu items are cached per
    | user/workspace combination to improve performance on repeated requests.
    |
    */

    'admin_menu' => [
        // Whether to enable caching for static menu items.
        // Set to false during development for instant menu updates.
        'cache_enabled' => env('CORE_ADMIN_MENU_CACHE', true),

        // Cache TTL in seconds (default: 5 minutes).
        // Lower values mean more frequent cache misses but fresher menus.
        'cache_ttl' => env('CORE_ADMIN_MENU_CACHE_TTL', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Resilience Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how the application handles Redis failures. When Redis becomes
    | unavailable, the system can either silently fall back to database storage
    | or throw an exception.
    |
    */

    'storage' => [
        // Whether to silently fall back to database when Redis fails.
        // Set to false to throw exceptions on Redis failure.
        'silent_fallback' => env('CORE_STORAGE_SILENT_FALLBACK', true),

        // Log level for fallback events: 'debug', 'info', 'notice', 'warning', 'error', 'critical'
        'fallback_log_level' => env('CORE_STORAGE_FALLBACK_LOG_LEVEL', 'warning'),

        // Whether to dispatch RedisFallbackActivated events for monitoring/alerting
        'dispatch_fallback_events' => env('CORE_STORAGE_DISPATCH_EVENTS', true),

        /*
        |----------------------------------------------------------------------
        | Circuit Breaker Configuration
        |----------------------------------------------------------------------
        |
        | The circuit breaker prevents cascading failures when Redis becomes
        | unavailable. When failures exceed the threshold, the circuit opens
        | and requests go directly to the fallback, avoiding repeated
        | connection attempts that slow down the application.
        |
        */

        'circuit_breaker' => [
            // Enable/disable the circuit breaker
            'enabled' => env('CORE_STORAGE_CIRCUIT_BREAKER_ENABLED', true),

            // Number of failures before opening the circuit
            'failure_threshold' => env('CORE_STORAGE_CIRCUIT_BREAKER_FAILURES', 5),

            // Seconds to wait before attempting recovery (half-open state)
            'recovery_timeout' => env('CORE_STORAGE_CIRCUIT_BREAKER_RECOVERY', 30),

            // Number of successful operations to close the circuit
            'success_threshold' => env('CORE_STORAGE_CIRCUIT_BREAKER_SUCCESSES', 2),

            // Cache driver for storing circuit breaker state (use non-Redis driver)
            'state_driver' => env('CORE_STORAGE_CIRCUIT_BREAKER_DRIVER', 'file'),
        ],

        /*
        |----------------------------------------------------------------------
        | Storage Metrics Configuration
        |----------------------------------------------------------------------
        |
        | Storage metrics collect information about cache operations including
        | hit/miss rates, latencies, and fallback activations. Use these
        | metrics for monitoring cache health and performance tuning.
        |
        */

        'metrics' => [
            // Enable/disable metrics collection
            'enabled' => env('CORE_STORAGE_METRICS_ENABLED', true),

            // Maximum latency samples to keep per driver (for percentile calculations)
            'max_samples' => env('CORE_STORAGE_METRICS_MAX_SAMPLES', 1000),

            // Whether to log metrics events
            'log_enabled' => env('CORE_STORAGE_METRICS_LOG', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Service Configuration
    |--------------------------------------------------------------------------
    |
    | Configure service discovery and dependency resolution. Services are
    | discovered by scanning module paths for classes implementing
    | ServiceDefinition.
    |
    */

    'services' => [
        // Whether to cache service discovery results
        'cache_discovery' => env('CORE_SERVICES_CACHE_DISCOVERY', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Language & Translation Configuration
    |--------------------------------------------------------------------------
    |
    | Configure translation fallback chains and missing key validation.
    | The fallback chain allows regional locales to fall back to their base
    | locale before using the application's fallback locale.
    |
    | Example chain: en_GB -> en -> fallback_locale (from config/app.php)
    |
    */

    'lang' => [
        // Enable locale chain fallback (e.g., en_GB -> en -> fallback)
        // When true, regional locales like 'en_GB' will first try 'en' before
        // falling back to the application's fallback_locale.
        'fallback_chain' => env('CORE_LANG_FALLBACK_CHAIN', true),

        // Warn about missing translation keys in development environments.
        // Set to true to always enable, false to always disable, or leave
        // null to auto-enable in local/development/testing environments.
        'validate_keys' => env('CORE_LANG_VALIDATE_KEYS'),

        // Log missing translation keys when validation is enabled.
        'log_missing_keys' => env('CORE_LANG_LOG_MISSING_KEYS', true),

        // Log level for missing translation key warnings.
        // Options: 'debug', 'info', 'notice', 'warning', 'error', 'critical'
        'missing_key_log_level' => env('CORE_LANG_MISSING_KEY_LOG_LEVEL', 'debug'),

        // Enable ICU message format support.
        // Requires the PHP intl extension for full functionality.
        // When disabled, ICU patterns will use basic placeholder replacement.
        'icu_enabled' => env('CORE_LANG_ICU_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Bouncer Action Gate Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the action whitelisting system. Philosophy: "If it wasn't
    | trained, it doesn't exist." Every controller action must be explicitly
    | permitted. Unknown actions are blocked (production) or prompt for
    | approval (training mode).
    |
    */

    'bouncer' => [
        // Enable training mode to allow approving new actions interactively.
        // In production, this should be false to enforce strict whitelisting.
        // In development/staging, enable to train the system with valid actions.
        'training_mode' => env('CORE_BOUNCER_TRAINING_MODE', false),

        // Whether to enable the action gate middleware.
        // Set to false to completely disable action whitelisting.
        'enabled' => env('CORE_BOUNCER_ENABLED', true),

        // Guards that should have action gating applied.
        // Actions on routes using these middleware groups will be checked.
        'guarded_middleware' => ['web', 'admin', 'api', 'client'],

        // Routes matching these patterns will bypass the action gate.
        // Use for login pages, public assets, health checks, etc.
        'bypass_patterns' => [
            'login',
            'logout',
            'register',
            'password/*',
            'sanctum/*',
            'livewire/*',
            '_debugbar/*',
            'horizon/*',
            'telescope/*',
        ],

        // Number of days to retain action request logs.
        // Set to 0 to disable automatic pruning.
        'log_retention_days' => env('CORE_BOUNCER_LOG_RETENTION', 30),

        // Whether to log allowed requests (can generate many records).
        // Recommended: false in production, true during training.
        'log_allowed_requests' => env('CORE_BOUNCER_LOG_ALLOWED', false),

        /*
        |----------------------------------------------------------------------
        | Honeypot Configuration
        |----------------------------------------------------------------------
        |
        | Configure the honeypot system that traps bots ignoring robots.txt.
        | Paths listed in robots.txt as disallowed are monitored; any request
        | indicates a bot that doesn't respect robots.txt.
        |
        */

        'honeypot' => [
            // Whether to auto-block IPs that hit critical honeypot paths.
            // When enabled, IPs hitting paths like /admin or /.env are blocked.
            // Set to false to require manual review of all honeypot hits.
            'auto_block_critical' => env('CORE_BOUNCER_HONEYPOT_AUTO_BLOCK', true),

            // Rate limiting for honeypot logging to prevent DoS via log flooding.
            // Maximum number of log entries per IP within the time window.
            'rate_limit_max' => env('CORE_BOUNCER_HONEYPOT_RATE_LIMIT_MAX', 10),

            // Rate limit time window in seconds (default: 60 = 1 minute).
            'rate_limit_window' => env('CORE_BOUNCER_HONEYPOT_RATE_LIMIT_WINDOW', 60),

            // Severity levels for honeypot paths.
            // 'critical' - Active probing (admin panels, config files).
            // 'warning' - General robots.txt violation.
            'severity_levels' => [
                'critical' => env('CORE_BOUNCER_HONEYPOT_SEVERITY_CRITICAL', 'critical'),
                'warning' => env('CORE_BOUNCER_HONEYPOT_SEVERITY_WARNING', 'warning'),
            ],

            // Paths that indicate critical/malicious probing.
            // Requests to these paths result in 'critical' severity.
            // Supports prefix matching (e.g., 'admin' matches '/admin', '/admin/login').
            'critical_paths' => [
                'admin',
                'wp-admin',
                'wp-login.php',
                'administrator',
                'phpmyadmin',
                '.env',
                '.git',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Workspace Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configure workspace-scoped caching for multi-tenant resources.
    | Models using the BelongsToWorkspace trait can cache their collections
    | with automatic invalidation when records are created, updated, or deleted.
    |
    | The cache system supports both tagged cache stores (Redis, Memcached)
    | and non-tagged stores (file, database, array). Tagged stores provide
    | more efficient cache invalidation.
    |
    */

    'workspace_cache' => [
        // Whether to enable workspace-scoped caching.
        // Set to false to completely disable caching (all queries hit the database).
        'enabled' => env('CORE_WORKSPACE_CACHE_ENABLED', true),

        // Default TTL in seconds for cached workspace queries.
        // Individual queries can override this with their own TTL.
        'ttl' => env('CORE_WORKSPACE_CACHE_TTL', 300),

        // Cache key prefix to avoid collisions with other cache keys.
        // Change this if you need to separate cache data between deployments.
        'prefix' => env('CORE_WORKSPACE_CACHE_PREFIX', 'workspace_cache'),

        // Whether to use cache tags if available.
        // Tags provide more efficient cache invalidation (flush by workspace or model).
        // Only works with tag-supporting stores (Redis, Memcached).
        // Set to false to always use key-based cache management.
        'use_tags' => env('CORE_WORKSPACE_CACHE_USE_TAGS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Activity Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure activity logging for audit trails across modules.
    | Uses spatie/laravel-activitylog under the hood with workspace-aware
    | enhancements for multi-tenant environments.
    |
    | Models can use the Core\Activity\Concerns\LogsActivity trait to
    | automatically log create, update, and delete operations.
    |
    */

    'activity' => [
        // Whether to enable activity logging globally.
        // Set to false to completely disable activity logging.
        'enabled' => env('CORE_ACTIVITY_ENABLED', true),

        // The log name to use for activities.
        // Different log names can be used to separate activities by context.
        'log_name' => env('CORE_ACTIVITY_LOG_NAME', 'default'),

        // Whether to include workspace_id in activity properties.
        // Enable this in multi-tenant applications to scope activities per workspace.
        'include_workspace' => env('CORE_ACTIVITY_INCLUDE_WORKSPACE', true),

        // Default events to log when using the LogsActivity trait.
        // Models can override this with the $activityLogEvents property.
        'default_events' => ['created', 'updated', 'deleted'],

        // Number of days to retain activity logs.
        // Use the activity:prune command to clean up old logs.
        // Set to 0 to disable automatic pruning.
        'retention_days' => env('CORE_ACTIVITY_RETENTION_DAYS', 90),

        // Custom Activity model class (optional).
        // Set this to use a custom Activity model with additional scopes.
        // Default: Core\Activity\Models\Activity::class
        'activity_model' => env('CORE_ACTIVITY_MODEL', \Core\Activity\Models\Activity::class),
    ],

];
