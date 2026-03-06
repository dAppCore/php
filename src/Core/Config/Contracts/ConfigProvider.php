<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Config\Contracts;

use Core\Config\Models\Channel;

/**
 * Interface for virtual configuration providers.
 *
 * Configuration providers supply values for config keys without database storage.
 * They enable modules to expose their runtime data through the config system,
 * allowing for consistent access patterns across all configuration sources.
 *
 * ## When to Use
 *
 * Use ConfigProvider when you have module data that should be accessible via
 * the config system but doesn't need to be stored in the database:
 *
 * - Module-specific settings computed at runtime
 * - Aggregated data from multiple sources
 * - Dynamic values that change per-request
 *
 * ## Pattern Matching
 *
 * Providers are matched against key patterns using wildcard syntax:
 * - `bio.*` - Matches all keys starting with "bio."
 * - `theme.colors.*` - Matches nested keys under "theme.colors"
 * - `exact.key` - Matches only the exact key
 *
 * ## Registration
 *
 * Register providers via ConfigResolver:
 *
 * ```php
 * $resolver->registerProvider('bio.*', new BioConfigProvider());
 * ```
 *
 * ## Example Implementation
 *
 * ```php
 * class BioConfigProvider implements ConfigProvider
 * {
 *     public function pattern(): string
 *     {
 *         return 'bio.*';
 *     }
 *
 *     public function resolve(
 *         string $keyCode,
 *         ?object $workspace,
 *         string|Channel|null $channel
 *     ): mixed {
 *         // Extract the specific key (e.g., "bio.theme" -> "theme")
 *         $subKey = substr($keyCode, 4);
 *
 *         return match ($subKey) {
 *             'theme' => $this->getTheme($workspace),
 *             'layout' => $this->getLayout($workspace),
 *             default => null,
 *         };
 *     }
 * }
 * ```
 *
 *
 * @see \Core\Config\ConfigResolver::registerProvider()
 */
interface ConfigProvider
{
    /**
     * Get the key pattern this provider handles.
     *
     * Supports wildcards:
     * - `*` matches any characters
     * - `bio.*` matches "bio.theme", "bio.colors.primary", etc.
     *
     * @return string The key pattern (e.g., 'bio.*', 'theme.colors.*')
     */
    public function pattern(): string;

    /**
     * Resolve a config value for the given key.
     *
     * Called when a key matches this provider's pattern. Return null if the
     * provider cannot supply a value for this specific key, allowing other
     * providers or the database to supply the value.
     *
     * @param  string  $keyCode  The full config key being resolved
     * @param  object|null  $workspace  Workspace model instance or null for system scope
     * @param  string|Channel|null  $channel  Channel code or object
     * @return mixed The config value, or null if not provided
     */
    public function resolve(
        string $keyCode,
        ?object $workspace,
        string|Channel|null $channel
    ): mixed;
}
