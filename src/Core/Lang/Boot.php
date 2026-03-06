<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Lang;

/**
 * Core Lang Module Boot.
 *
 * This module provides enhanced translation functionality for the Core PHP framework.
 * Translation loading and configuration is handled by LangServiceProvider which
 * is auto-discovered via Laravel's package discovery.
 *
 * @see LangServiceProvider For the main service provider implementation
 *
 * Usage in Blade: {{ __('core::core.brand.name') }}
 * Usage in PHP:   __('core::core.brand.name')
 *
 * Override translations by publishing to resources/lang/vendor/core/
 *   php artisan vendor:publish --tag=core-translations
 *
 * Features:
 * - Auto-discovered service provider (no manual registration needed)
 * - Fallback locale chain support (e.g., en_GB -> en -> fallback)
 * - Missing translation key validation in development
 *
 * Configuration in config/core.php:
 *   'lang' => [
 *       'fallback_chain' => true,           // Enable locale chain fallback
 *       'validate_keys' => null,            // Auto-enable in local/development/testing
 *       'log_missing_keys' => true,         // Log missing keys
 *       'missing_key_log_level' => 'debug', // Log level for missing keys
 *   ]
 */
class Boot
{
    // This class is intentionally empty.
    // All functionality is provided by LangServiceProvider.
    // This file exists for documentation and potential future event-based hooks.
}
