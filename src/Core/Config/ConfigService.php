<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Config;

use Core\Config\Enums\ConfigType;
use Core\Config\Events\ConfigChanged;
use Core\Config\Events\ConfigInvalidated;
use Core\Config\Events\ConfigLocked;
use Core\Config\Models\Channel;
use Core\Config\Models\ConfigKey;
use Core\Config\Models\ConfigProfile;
use Core\Config\Models\ConfigResolved;
use Core\Config\Models\ConfigValue;

/**
 * Configuration service - main API.
 *
 * Single hash: ConfigResolver::$values
 * Read path: hash lookup → lazy load scope → compute if needed
 *
 * ## Usage
 *
 * ```php
 * $config = app(ConfigService::class);
 * $value = $config->get('cdn.bunny.api_key', $workspace);
 * $config->set('cdn.bunny.api_key', 'new-value', $profile);
 *
 * // Module Boot.php - provide runtime value (no DB)
 * $config->provide('mymodule.api_key', env('MYMODULE_API_KEY'));
 * ```
 *
 * ## Cache Invalidation Strategy
 *
 * The Config module uses a two-tier caching system:
 *
 * ### Tier 1: In-Memory Hash (Process-Scoped)
 * - `ConfigResolver::$values` - Static array holding all config values
 * - Cleared on process termination (dies with the request)
 * - Cleared explicitly via `ConfigResolver::clearAll()` or `ConfigResolver::clear($key)`
 *
 * ### Tier 2: Database Resolved Table (Persistent)
 * - `config_resolved` table - Materialised config resolution
 * - Survives across requests, shared between all processes
 * - Cleared via `ConfigResolved::clearScope()`, `clearWorkspace()`, or `clearKey()`
 *
 * ### Invalidation Triggers
 *
 * 1. **On Config Change (`set()`):**
 *    - Clears the specific key from both hash and database
 *    - Re-primes the key for the affected scope
 *    - Dispatches `ConfigChanged` event for module hooks
 *
 * 2. **On Lock/Unlock:**
 *    - Re-primes the key (lock affects all child scopes)
 *    - Dispatches `ConfigLocked` event
 *
 * 3. **Manual Invalidation:**
 *    - `invalidateWorkspace($workspace)` - Clears all config for a workspace
 *    - `invalidateKey($key)` - Clears a key across all scopes
 *    - Both dispatch `ConfigInvalidated` event
 *
 * 4. **Full Re-prime:**
 *    - `prime($workspace)` - Clears and recomputes all config for a scope
 *    - `primeAll()` - Primes system config + all workspaces (scheduled job)
 *
 * ### Lazy Loading
 *
 * When a key is not found in the hash:
 * 1. If scope not loaded, `loadScope()` loads all resolved values for the scope
 * 2. If still not found, `resolve()` computes and stores the value
 * 3. Result is stored in both hash (for current request) and database (persistent)
 *
 * ### Events for Module Integration
 *
 * Modules can listen to cache events to refresh their own caches:
 * - `ConfigChanged` - Fired when a config value is set/updated
 * - `ConfigLocked` - Fired when a config value is locked
 * - `ConfigInvalidated` - Fired when cache is manually invalidated
 *
 * ```php
 * // In your module's Boot.php
 * public static array $listens = [
 *     ConfigChanged::class => 'onConfigChanged',
 * ];
 *
 * public function onConfigChanged(ConfigChanged $event): void
 * {
 *     if ($event->keyCode === 'mymodule.api_key') {
 *         $this->refreshApiClient();
 *     }
 * }
 * ```
 *
 * @see ConfigResolver For the caching hash implementation
 * @see ConfigResolved For the database cache model
 * @see ConfigChanged Event fired on config changes
 * @see ConfigInvalidated Event fired on cache invalidation
 */
class ConfigService
{
    /**
     * Current workspace context (Workspace model instance or null for system scope).
     */
    protected ?object $workspace = null;

    protected ?Channel $channel = null;

    public function __construct(
        protected ConfigResolver $resolver,
    ) {}

    /**
     * Set the current context (called by middleware).
     *
     * @param  object|null  $workspace  Workspace model instance or null for system scope
     */
    public function setContext(?object $workspace, ?Channel $channel = null): void
    {
        $this->workspace = $workspace;
        $this->channel = $channel;
    }

    /**
     * Get current workspace context.
     *
     * @return object|null Workspace model instance or null
     */
    public function getWorkspace(): ?object
    {
        return $this->workspace;
    }

    /**
     * Get a config value.
     *
     * Context (workspace/channel) is set by middleware via setContext().
     * This is just key/value - simple.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $result = $this->resolve($key, $this->workspace, $this->channel);

        return $result->get($default);
    }

    /**
     * Get config for a specific workspace (admin use only).
     *
     * Use this when you need another workspace's settings - requires explicit intent.
     *
     * @param  object  $workspace  Workspace model instance
     */
    public function getForWorkspace(string $key, object $workspace, mixed $default = null): mixed
    {
        $result = $this->resolve($key, $workspace, null);

        return $result->get($default);
    }

    /**
     * Get a resolved ConfigResult.
     *
     * Read path:
     * 1. Hash lookup (O(1))
     * 2. Lazy load scope if not loaded (1 query)
     * 3. Hash lookup again
     * 4. Compute via resolver if still not found (lazy prime)
     *
     * @param  object|null  $workspace  Workspace model instance or null for system scope
     * @param  string|Channel|null  $channel  Channel code or object
     */
    public function resolve(
        string $key,
        ?object $workspace = null,
        string|Channel|null $channel = null,
    ): ConfigResult {
        $workspaceId = $workspace?->id;
        $channelId = $this->resolveChannelId($channel, $workspace);

        // 1. Check hash (O(1))
        if (ConfigResolver::has($key)) {
            // Get full result from ConfigResolved (indexed lookup with metadata)
            $resolved = ConfigResolved::lookup($key, $workspaceId, $channelId);

            if ($resolved !== null) {
                return $resolved->toResult();
            }

            // Fallback: value in hash but not in DB (runtime provided)
            return ConfigResult::found(
                key: $key,
                value: ConfigResolver::get($key),
                type: ConfigType::STRING,
                locked: false,
            );
        }

        // 2. Scope not loaded - lazy load entire scope
        if (! ConfigResolver::isLoaded()) {
            $this->loadScope($workspaceId, $channelId);

            // Check hash again
            if (ConfigResolver::has($key)) {
                $resolved = ConfigResolved::lookup($key, $workspaceId, $channelId);

                if ($resolved !== null) {
                    return $resolved->toResult();
                }

                return ConfigResult::found(
                    key: $key,
                    value: ConfigResolver::get($key),
                    type: ConfigType::STRING,
                    locked: false,
                );
            }
        }

        // 3. Try JSON sub-key extraction
        $subKeyResult = $this->resolveJsonSubKey($key, $workspace, $channel);
        if ($subKeyResult->found) {
            return $subKeyResult;
        }

        // 4. Check virtual providers
        $virtualValue = $this->resolver->resolveFromProviders($key, $workspace, $channel);
        if ($virtualValue !== null) {
            $keyModel = ConfigKey::byCode($key);
            $type = $keyModel?->type ?? ConfigType::STRING;

            // Store in hash for next read
            ConfigResolver::set($key, $virtualValue);

            return ConfigResult::virtual(
                key: $key,
                value: $virtualValue,
                type: $type,
            );
        }

        // 5. Lazy prime: compute via resolver
        $result = $this->resolver->resolve($key, $workspace, $channel);

        // Store in hash
        ConfigResolver::set($key, $result->value);

        // Store in DB for future requests
        if ($result->isConfigured()) {
            ConfigResolved::store(
                keyCode: $key,
                value: $result->value,
                type: $result->type,
                workspaceId: $workspaceId,
                channelId: $channelId,
                locked: $result->locked,
                sourceProfileId: $result->profileId,
                sourceChannelId: $result->channelId,
                virtual: $result->virtual,
            );
        }

        return $result;
    }

    /**
     * Load a scope into the hash from database.
     */
    protected function loadScope(?int $workspaceId, ?int $channelId): void
    {
        $resolved = ConfigResolved::forScope($workspaceId, $channelId);

        foreach ($resolved as $row) {
            ConfigResolver::set($row->key_code, $row->getTypedValue());
        }

        ConfigResolver::markLoaded();
    }

    /**
     * Try to resolve a JSON sub-key (e.g., "website.title" from "website" JSON).
     *
     * @param  object|null  $workspace  Workspace model instance or null for system scope
     */
    protected function resolveJsonSubKey(
        string $keyCode,
        ?object $workspace,
        string|Channel|null $channel,
    ): ConfigResult {
        $parts = explode('.', $keyCode);

        // Try progressively shorter parent keys
        for ($i = count($parts) - 1; $i > 0; $i--) {
            $parentKey = implode('.', array_slice($parts, 0, $i));
            $subPath = implode('.', array_slice($parts, $i));

            $workspaceId = $workspace?->id;
            $channelId = $this->resolveChannelId($channel, $workspace);

            $resolved = ConfigResolved::lookup($parentKey, $workspaceId, $channelId);

            if ($resolved !== null && is_array($resolved->value)) {
                $subValue = data_get($resolved->value, $subPath);

                if ($subValue !== null) {
                    $result = $resolved->toResult();

                    return new ConfigResult(
                        key: $keyCode,
                        value: $subValue,
                        type: $result->type,
                        found: true,
                        locked: $result->locked,
                        virtual: $result->virtual,
                        resolvedFrom: $result->resolvedFrom,
                        profileId: $result->profileId,
                        channelId: $result->channelId,
                    );
                }
            }
        }

        return ConfigResult::unconfigured($keyCode);
    }

    /**
     * Check if a key (or prefix) is configured.
     *
     * Uses current context set by middleware.
     */
    public function isConfigured(string $keyOrPrefix): bool
    {
        $workspaceId = $this->workspace?->id;
        $channelId = $this->channel?->id;

        // Check if it's a direct key
        $resolved = ConfigResolved::lookup($keyOrPrefix, $workspaceId, $channelId);
        if ($resolved !== null && $resolved->value !== null) {
            return true;
        }

        // Check as prefix - single EXISTS query
        // Escape LIKE wildcards to prevent unintended pattern matching
        $escapedPrefix = str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $keyOrPrefix);

        return ConfigResolved::where('workspace_id', $workspaceId)
            ->where('channel_id', $channelId)
            ->where('key_code', 'LIKE', "{$escapedPrefix}.%")
            ->whereNotNull('value')
            ->exists();
    }

    /**
     * Set a config value.
     *
     * Updates config_values (source of truth), then re-primes affected scope.
     * Fires ConfigChanged event for invalidation hooks.
     *
     * @param  string|Channel|null  $channel  Channel code or object
     *
     * @throws \InvalidArgumentException If key is unknown or value type is invalid
     */
    public function set(
        string $keyCode,
        mixed $value,
        ConfigProfile $profile,
        bool $locked = false,
        string|Channel|null $channel = null,
    ): void {
        // Get key from DB (only during set, not reads)
        $key = ConfigKey::byCode($keyCode);

        if ($key === null) {
            throw new \InvalidArgumentException("Unknown config key: {$keyCode}");
        }

        // Validate value type against schema
        $this->validateValueType($value, $key->type, $keyCode);

        $channelId = $this->resolveChannelId($channel, null);

        // Capture previous value for event
        $previousValue = ConfigValue::findValue($profile->id, $key->id, $channelId)?->value;

        // Update source of truth
        ConfigValue::setValue($profile->id, $key->id, $value, $locked, null, $channelId);

        // Re-prime affected scope
        $workspaceId = match ($profile->scope_type) {
            Enums\ScopeType::WORKSPACE => $profile->scope_id,
            default => null,
        };

        $this->primeKey($keyCode, $workspaceId, $channelId);

        // Fire event for module hooks
        ConfigChanged::dispatch($keyCode, $value, $previousValue, $profile, $channelId);
    }

    /**
     * Lock a config value (FINAL - child cannot override).
     * Fires ConfigLocked event.
     */
    public function lock(string $keyCode, ConfigProfile $profile, string|Channel|null $channel = null): void
    {
        // Get key from DB (only during lock, not reads)
        $key = ConfigKey::byCode($keyCode);

        if ($key === null) {
            throw new \InvalidArgumentException("Unknown config key: {$keyCode}");
        }

        $channelId = $this->resolveChannelId($channel, null);
        $value = ConfigValue::findValue($profile->id, $key->id, $channelId);

        if ($value === null) {
            throw new \InvalidArgumentException("No value set for {$keyCode} in profile {$profile->id}");
        }

        $value->update(['locked' => true]);

        // Re-prime - lock affects all child scopes
        $this->primeKey($keyCode);

        // Fire event for module hooks
        ConfigLocked::dispatch($keyCode, $profile, $channelId);
    }

    /**
     * Unlock a config value.
     */
    public function unlock(string $keyCode, ConfigProfile $profile, string|Channel|null $channel = null): void
    {
        // Get key from DB (only during unlock, not reads)
        $key = ConfigKey::byCode($keyCode);

        if ($key === null) {
            return;
        }

        $channelId = $this->resolveChannelId($channel, null);
        $value = ConfigValue::findValue($profile->id, $key->id, $channelId);

        if ($value === null) {
            return;
        }

        $value->update(['locked' => false]);

        // Re-prime
        $this->primeKey($keyCode);
    }

    /**
     * Register a virtual provider.
     *
     * Virtual providers supply config values from module data
     * without database storage.
     *
     * @param  string  $pattern  Key pattern (supports * wildcard)
     * @param  callable  $provider  fn(string $key, ?Workspace, ?Channel): mixed
     */
    public function virtual(string $pattern, callable $provider): void
    {
        $this->resolver->registerProvider($pattern, $provider);
    }

    /**
     * Get all config values for a workspace.
     *
     * @param  object|null  $workspace  Workspace model instance or null for system scope
     * @return array<string, mixed>
     */
    public function all(?object $workspace = null, string|Channel|null $channel = null): array
    {
        $workspaceId = $workspace?->id;
        $channelId = $this->resolveChannelId($channel, $workspace);

        $resolved = ConfigResolved::forScope($workspaceId, $channelId);

        $values = [];
        foreach ($resolved as $row) {
            $values[$row->key_code] = $row->getTypedValue();
        }

        return $values;
    }

    /**
     * Get all config values for a category.
     *
     * @param  object|null  $workspace  Workspace model instance or null for system scope
     * @return array<string, mixed>
     */
    public function category(
        string $category,
        ?object $workspace = null,
        string|Channel|null $channel = null,
    ): array {
        $workspaceId = $workspace?->id;
        $channelId = $this->resolveChannelId($channel, $workspace);

        // Escape LIKE wildcards to prevent unintended pattern matching
        $escapedCategory = str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $category);

        $resolved = ConfigResolved::where('workspace_id', $workspaceId)
            ->where('channel_id', $channelId)
            ->where('key_code', 'LIKE', "{$escapedCategory}.%")
            ->get();

        $values = [];
        foreach ($resolved as $row) {
            $values[$row->key_code] = $row->getTypedValue();
        }

        return $values;
    }

    /**
     * Prime the resolved table for a workspace.
     *
     * This is THE computation - runs full resolution and stores results.
     * Call after workspace creation, config changes, or on schedule.
     *
     * Populates both hash (process-scoped) and database (persistent).
     *
     * ## When to Call Prime
     *
     * - After creating a new workspace
     * - After bulk config changes (migrations, imports)
     * - From a scheduled job (`config:prime` command)
     * - After significant profile hierarchy changes
     *
     * ## What Prime Does
     *
     * 1. Clears existing resolved values (hash + DB) for the scope
     * 2. Runs full resolution for all config keys
     * 3. Stores results in both hash and database
     * 4. Marks hash as "loaded" to prevent re-loading
     *
     * @param  object|null  $workspace  Workspace model instance or null for system scope
     */
    public function prime(?object $workspace = null, string|Channel|null $channel = null): void
    {
        $workspaceId = $workspace?->id;
        $channelId = $this->resolveChannelId($channel, $workspace);

        // Clear existing resolved values (hash + DB)
        ConfigResolver::clearAll();
        ConfigResolved::clearScope($workspaceId, $channelId);

        // Run full resolution
        $results = $this->resolver->resolveAll($workspace, $channel);

        // Store all resolved values (hash + DB)
        foreach ($results as $code => $result) {
            // Store in hash (process-scoped)
            ConfigResolver::set($code, $result->value);

            // Store in database (persistent)
            ConfigResolved::store(
                keyCode: $code,
                value: $result->value,
                type: $result->type,
                workspaceId: $workspaceId,
                channelId: $channelId,
                locked: $result->locked,
                sourceProfileId: $result->profileId,
                sourceChannelId: $result->channelId,
                virtual: $result->virtual,
            );
        }

        // Mark hash as loaded
        ConfigResolver::markLoaded();
    }

    /**
     * Prime a single key across all affected scopes.
     *
     * Clears and re-computes a specific key in both hash and database.
     */
    public function primeKey(string $keyCode, ?int $workspaceId = null, ?int $channelId = null): void
    {
        // Clear from hash (pattern match)
        ConfigResolver::clear($keyCode);

        // Clear from database
        ConfigResolved::where('key_code', $keyCode)
            ->when($workspaceId !== null, fn ($q) => $q->where('workspace_id', $workspaceId))
            ->when($channelId !== null, fn ($q) => $q->where('channel_id', $channelId))
            ->delete();

        // Re-compute this key for the affected scope
        $workspace = null;
        if ($workspaceId !== null && class_exists(\Core\Tenant\Models\Workspace::class)) {
            $workspace = \Core\Tenant\Models\Workspace::find($workspaceId);
        }
        $channel = $channelId ? Channel::find($channelId) : null;

        $result = $this->resolver->resolve($keyCode, $workspace, $channel);

        // Store in hash (process-scoped)
        ConfigResolver::set($keyCode, $result->value);

        // Store in database (persistent)
        ConfigResolved::store(
            keyCode: $keyCode,
            value: $result->value,
            type: $result->type,
            workspaceId: $workspaceId,
            channelId: $channelId,
            locked: $result->locked,
            sourceProfileId: $result->profileId,
            sourceChannelId: $result->channelId,
            virtual: $result->virtual,
        );
    }

    /**
     * Prime cache for all workspaces.
     *
     * Run this from a scheduled command or queue job.
     * Requires Core\Tenant module to prime workspace-level config.
     */
    public function primeAll(): void
    {
        // Prime system config
        $this->prime(null);

        // Prime each workspace (requires Tenant module)
        if (class_exists(\Core\Tenant\Models\Workspace::class)) {
            \Core\Tenant\Models\Workspace::chunk(100, function ($workspaces) {
                foreach ($workspaces as $workspace) {
                    $this->prime($workspace);
                }
            });
        }
    }

    /**
     * Invalidate (clear) resolved config for a workspace.
     *
     * Clears both hash and database. Next read will lazy-prime.
     * Fires ConfigInvalidated event.
     *
     * ## Cache Invalidation Behaviour
     *
     * This method performs a "soft" invalidation:
     * - Clears the in-memory hash (immediate effect)
     * - Clears the database resolved table (persistent effect)
     * - Does NOT re-compute values immediately
     * - Values are lazy-loaded on next read (lazy-prime)
     *
     * Use `prime()` instead if you need immediate re-computation.
     *
     * ## Listening for Invalidation
     *
     * ```php
     * use Core\Config\Events\ConfigInvalidated;
     *
     * public function handle(ConfigInvalidated $event): void
     * {
     *     if ($event->isFull()) {
     *         // Full invalidation - clear all module caches
     *     } elseif ($event->affectsKey('mymodule.setting')) {
     *         // Specific key was invalidated
     *     }
     * }
     * ```
     *
     * @param  object|null  $workspace  Workspace model instance or null for system scope
     */
    public function invalidateWorkspace(?object $workspace = null): void
    {
        $workspaceId = $workspace?->id;

        // Clear hash (process-scoped)
        ConfigResolver::clearAll();

        // Clear database (persistent)
        ConfigResolved::clearWorkspace($workspaceId);

        ConfigInvalidated::dispatch(null, $workspaceId, null);
    }

    /**
     * Invalidate (clear) resolved config for a key.
     *
     * Clears both hash and database. Next read will lazy-prime.
     * Fires ConfigInvalidated event.
     */
    public function invalidateKey(string $key): void
    {
        // Clear hash (process-scoped)
        ConfigResolver::clear($key);

        // Clear database (persistent)
        ConfigResolved::clearKey($key);

        ConfigInvalidated::dispatch($key, null, null);
    }

    /**
     * Resolve channel to ID.
     */
    protected function resolveChannelId(string|Channel|null $channel, ?Workspace $workspace): ?int
    {
        if ($channel === null) {
            return null;
        }

        if ($channel instanceof Channel) {
            return $channel->id;
        }

        $channelModel = Channel::byCode($channel, $workspace?->id);

        return $channelModel?->id;
    }

    /**
     * Ensure a config key exists (for dynamic registration).
     */
    public function ensureKey(
        string $code,
        ConfigType $type,
        string $category,
        ?string $description = null,
        mixed $default = null,
    ): ConfigKey {
        return ConfigKey::firstOrCreate(
            ['code' => $code],
            [
                'type' => $type,
                'category' => $category,
                'description' => $description,
                'default_value' => $default,
            ]
        );
    }

    /**
     * Register a config key if it doesn't exist.
     *
     * Convenience method for Boot.php files.
     * Note: This persists to database. For runtime-only values, use provide().
     */
    public function register(
        string $code,
        string $type,
        string $category,
        ?string $description = null,
        mixed $default = null,
    ): void {
        $this->ensureKey($code, ConfigType::from($type), $category, $description, $default);
    }

    /**
     * Provide a runtime value.
     *
     * Modules call this to share settings with other code in the process.
     * Process-scoped, not persisted to database. Dies with the request.
     *
     * Usage in Boot.php:
     *   $config->provide('mymodule.api_key', env('MYMODULE_API_KEY'));
     *   $config->provide('mymodule.timeout', 30, 'int');
     *
     * @param  string  $code  Key code (e.g., 'mymodule.api_key')
     * @param  mixed  $value  The value
     * @param  string|ConfigType  $type  Value type for casting (currently unused, value stored as-is)
     */
    public function provide(string $code, mixed $value, string|ConfigType $type = 'string'): void
    {
        // Runtime values just go in the hash (system scope)
        ConfigResolver::set($code, $value);
    }

    /**
     * Check if a runtime value has been provided.
     */
    public function hasProvided(string $code): bool
    {
        return ConfigResolver::has($code);
    }

    /**
     * Validate that a value matches the expected config type.
     *
     * @throws \InvalidArgumentException If value type is invalid
     */
    protected function validateValueType(mixed $value, ConfigType $type, string $keyCode): void
    {
        // Null is allowed for any type (represents unset)
        if ($value === null) {
            return;
        }

        $valid = match ($type) {
            ConfigType::STRING => is_string($value) || is_numeric($value),
            ConfigType::BOOL => is_bool($value) || in_array($value, [0, 1, '0', '1', 'true', 'false'], true),
            ConfigType::INT => is_int($value) || (is_string($value) && ctype_digit(ltrim($value, '-'))),
            ConfigType::FLOAT => is_float($value) || is_int($value) || is_numeric($value),
            ConfigType::ARRAY, ConfigType::JSON => is_array($value),
        };

        if (! $valid) {
            $actualType = get_debug_type($value);
            throw new \InvalidArgumentException(
                "Invalid value type for config key '{$keyCode}': expected {$type->value}, got {$actualType}"
            );
        }
    }
}
