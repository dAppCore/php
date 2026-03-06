<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Config;

use Core\Config\Contracts\ConfigProvider;
use Core\Config\Enums\ScopeType;
use Core\Config\Models\Channel;
use Core\Config\Models\ConfigKey;
use Core\Config\Models\ConfigProfile;
use Core\Config\Models\ConfigValue;
use Illuminate\Support\Collection;

/**
 * Configuration resolution engine.
 *
 * Single static hash for all config values:
 * - Runtime values from modules
 * - Resolved values from database
 * - All in one place, zero-DB reads after warmup
 *
 * Read path: $values[$key] ?? compute and store
 *
 * Resolution dimensions (when computing):
 * - Scope: workspace → org → system (most specific wins)
 * - Channel: specific → parent → null (most specific wins)
 *
 * Respects FINAL/locked declarations from parent scopes.
 */
class ConfigResolver
{
    /**
     * The hash. Key → value. That's it.
     *
     * @var array<string, mixed>
     */
    protected static array $values = [];

    /**
     * Whether the hash has been loaded.
     */
    protected static bool $loaded = false;

    /**
     * Registered virtual providers.
     *
     * Supports both ConfigProvider instances and callable functions.
     *
     * @var array<string, ConfigProvider|callable>
     */
    protected array $providers = [];

    // =========================================================================
    // THE HASH
    // =========================================================================

    /**
     * Get a value from the hash.
     */
    public static function get(string $key): mixed
    {
        return static::$values[$key] ?? null;
    }

    /**
     * Set a value in the hash.
     */
    public static function set(string $key, mixed $value): void
    {
        static::$values[$key] = $value;
    }

    /**
     * Check if a value exists in the hash.
     */
    public static function has(string $key): bool
    {
        return array_key_exists($key, static::$values);
    }

    /**
     * Clear keys matching a pattern (bi-directional).
     */
    public static function clear(string $pattern): void
    {
        static::$values = array_filter(
            static::$values,
            fn ($k) => ! str_contains($k, $pattern),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Clear entire hash.
     */
    public static function clearAll(): void
    {
        static::$values = [];
        static::$loaded = false;
    }

    /**
     * Get all values (for debugging).
     *
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        return static::$values;
    }

    /**
     * Check if hash has been loaded.
     */
    public static function isLoaded(): bool
    {
        return static::$loaded;
    }

    /**
     * Mark hash as loaded.
     */
    public static function markLoaded(): void
    {
        static::$loaded = true;
    }

    // =========================================================================
    // RESOLUTION ENGINE (only runs during lazy prime, not normal reads)
    // =========================================================================

    /**
     * Resolve a single key for a workspace and optional channel.
     *
     * NOTE: This is the expensive path - only called when lazy-priming.
     * Normal reads hit the hash directly via ConfigService.
     *
     * @param  object|null  $workspace  Workspace model instance or null for system scope
     * @param  string|Channel|null  $channel  Channel code or object
     */
    public function resolve(
        string $keyCode,
        ?object $workspace = null,
        string|Channel|null $channel = null,
    ): ConfigResult {
        // Get key definition (DB query - only during resolve, not normal reads)
        $key = ConfigKey::byCode($keyCode);

        if ($key === null) {
            // Try JSON sub-key extraction
            return $this->resolveJsonSubKey($keyCode, $workspace, $channel);
        }

        // Build chains
        $profileChain = $this->buildProfileChain($workspace);
        $channelChain = $this->buildChannelChain($channel, $workspace);

        // Batch load all values for this key
        $values = $this->batchLoadValues(
            $key->id,
            $profileChain->pluck('id')->all(),
            $channelChain->pluck('id')->all()
        );

        // Build resolution matrix (profile × channel combinations)
        $matrix = $this->buildResolutionMatrix($profileChain, $channelChain);

        // First pass: check for FINAL locks (from least specific scope)
        $lockedResult = $this->findFinalLock($matrix, $values, $keyCode, $key);
        if ($lockedResult !== null) {
            return $lockedResult;
        }

        // Second pass: find most specific value
        foreach ($matrix as $combo) {
            $value = $this->findValueInBatch($values, $combo['profile_id'], $combo['channel_id']);

            if ($value !== null) {
                return ConfigResult::found(
                    key: $keyCode,
                    value: $value->value,
                    type: $key->type,
                    locked: false,
                    resolvedFrom: $combo['scope_type'],
                    profileId: $combo['profile_id'],
                    channelId: $combo['channel_id'],
                );
            }
        }

        // Check virtual providers
        $virtualValue = $this->resolveFromProviders($keyCode, $workspace, $channel);
        if ($virtualValue !== null) {
            return ConfigResult::virtual(
                key: $keyCode,
                value: $virtualValue,
                type: $key->type,
            );
        }

        // No value found - return default
        return ConfigResult::notFound($keyCode, $key->getTypedDefault(), $key->type);
    }

    /**
     * Maximum recursion depth for JSON sub-key resolution.
     */
    protected const MAX_SUBKEY_DEPTH = 10;

    /**
     * Current recursion depth for sub-key resolution.
     */
    protected int $subKeyDepth = 0;

    /**
     * Try to resolve a JSON sub-key (e.g., "website.title" from "website" JSON).
     */
    /**
     * @param  object|null  $workspace  Workspace model instance or null for system scope
     */
    protected function resolveJsonSubKey(
        string $keyCode,
        ?object $workspace,
        string|Channel|null $channel,
    ): ConfigResult {
        // Guard against stack overflow from deep nesting
        if ($this->subKeyDepth >= self::MAX_SUBKEY_DEPTH) {
            return ConfigResult::unconfigured($keyCode);
        }

        $this->subKeyDepth++;

        try {
            $parts = explode('.', $keyCode);

            // Try progressively shorter parent keys
            for ($i = count($parts) - 1; $i > 0; $i--) {
                $parentKey = implode('.', array_slice($parts, 0, $i));
                $subPath = implode('.', array_slice($parts, $i));

                $parentResult = $this->resolve($parentKey, $workspace, $channel);

                if ($parentResult->found && is_array($parentResult->value)) {
                    $subValue = data_get($parentResult->value, $subPath);

                    if ($subValue !== null) {
                        return ConfigResult::found(
                            key: $keyCode,
                            value: $subValue,
                            type: $parentResult->type, // Inherit parent type
                            locked: $parentResult->locked,
                            resolvedFrom: $parentResult->resolvedFrom,
                            profileId: $parentResult->profileId,
                            channelId: $parentResult->channelId,
                        );
                    }
                }
            }

            return ConfigResult::unconfigured($keyCode);
        } finally {
            $this->subKeyDepth--;
        }
    }

    /**
     * Build the channel inheritance chain.
     *
     * @param  object|null  $workspace  Workspace model instance or null for system scope
     * @return Collection<int, Channel|null>
     */
    public function buildChannelChain(
        string|Channel|null $channel,
        ?object $workspace = null,
    ): Collection {
        $chain = new Collection;

        if ($channel === null) {
            // No channel specified - just null (applies to all)
            $chain->push(null);

            return $chain;
        }

        // Resolve channel code to model
        if (is_string($channel)) {
            $channel = Channel::byCode($channel, $workspace?->id);
        }

        if ($channel !== null) {
            // Add channel inheritance chain
            $chain = $chain->merge($channel->inheritanceChain());
        }

        // Always include null (all-channels fallback)
        $chain->push(null);

        return $chain;
    }

    /**
     * Batch load all values for a key across profiles and channels.
     *
     * @param  array<int>  $profileIds
     * @param  array<int|null>  $channelIds
     * @return Collection<int, ConfigValue>
     */
    protected function batchLoadValues(int $keyId, array $profileIds, array $channelIds): Collection
    {
        // Separate null from actual channel IDs for query
        $actualChannelIds = array_filter($channelIds, fn ($id) => $id !== null);

        return ConfigValue::where('key_id', $keyId)
            ->whereIn('profile_id', $profileIds)
            ->where(function ($query) use ($actualChannelIds) {
                $query->whereNull('channel_id');
                if (! empty($actualChannelIds)) {
                    $query->orWhereIn('channel_id', $actualChannelIds);
                }
            })
            ->get();
    }

    /**
     * Build resolution matrix (profile × channel combinations).
     *
     * Order: most specific first (workspace + specific channel)
     * to least specific (system + null channel).
     *
     * @return array<array{profile_id: int, channel_id: int|null, scope_type: ScopeType}>
     */
    protected function buildResolutionMatrix(Collection $profileChain, Collection $channelChain): array
    {
        $matrix = [];

        foreach ($profileChain as $profile) {
            foreach ($channelChain as $channel) {
                $matrix[] = [
                    'profile_id' => $profile->id,
                    'channel_id' => $channel?->id,
                    'scope_type' => $profile->scope_type,
                ];
            }
        }

        return $matrix;
    }

    /**
     * Find a FINAL lock in the resolution matrix.
     *
     * Checks from least specific (system) to find any lock that
     * would prevent more specific values from being used.
     */
    protected function findFinalLock(
        array $matrix,
        Collection $values,
        string $keyCode,
        ConfigKey $key,
    ): ?ConfigResult {
        // Reverse to check from least specific (system)
        $reversed = array_reverse($matrix);

        foreach ($reversed as $combo) {
            $value = $this->findValueInBatch($values, $combo['profile_id'], $combo['channel_id']);

            if ($value !== null && $value->isLocked()) {
                return ConfigResult::found(
                    key: $keyCode,
                    value: $value->value,
                    type: $key->type,
                    locked: true,
                    resolvedFrom: $combo['scope_type'],
                    profileId: $combo['profile_id'],
                    channelId: $combo['channel_id'],
                );
            }
        }

        return null;
    }

    /**
     * Find a value in the batch-loaded collection.
     */
    protected function findValueInBatch(Collection $values, int $profileId, ?int $channelId): ?ConfigValue
    {
        return $values->first(function (ConfigValue $value) use ($profileId, $channelId) {
            return $value->profile_id === $profileId
                && $value->channel_id === $channelId;
        });
    }

    /**
     * Register a virtual provider for a key pattern.
     *
     * Providers supply values from module data without database storage.
     * Accepts either a ConfigProvider instance or a callable.
     *
     * @param  string|ConfigProvider  $patternOrProvider  Key pattern (supports * wildcard) or ConfigProvider instance
     * @param  ConfigProvider|callable|null  $provider  ConfigProvider instance or fn(string $key, ?object $workspace, ?Channel $channel): mixed
     */
    public function registerProvider(string|ConfigProvider $patternOrProvider, ConfigProvider|callable|null $provider = null): void
    {
        // Support both new interface-based and legacy callable patterns
        if ($patternOrProvider instanceof ConfigProvider) {
            $this->providers[$patternOrProvider->pattern()] = $patternOrProvider;
        } elseif ($provider !== null) {
            $this->providers[$patternOrProvider] = $provider;
        }
    }

    /**
     * Resolve value from virtual providers.
     *
     * Supports both ConfigProvider instances and legacy callables.
     *
     * @param  object|null  $workspace  Workspace model instance or null for system scope
     */
    public function resolveFromProviders(
        string $keyCode,
        ?object $workspace,
        string|Channel|null $channel,
    ): mixed {
        foreach ($this->providers as $pattern => $provider) {
            if ($this->matchesPattern($keyCode, $pattern)) {
                // Support both ConfigProvider interface and legacy callable
                $value = $provider instanceof ConfigProvider
                    ? $provider->resolve($keyCode, $workspace, $channel)
                    : $provider($keyCode, $workspace, $channel);

                if ($value !== null) {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * Check if a key matches a provider pattern.
     */
    protected function matchesPattern(string $key, string $pattern): bool
    {
        if ($pattern === $key) {
            return true;
        }

        // Convert pattern to regex (e.g., "bio.*" → "^bio\..*$")
        $regex = '/^'.str_replace(['.', '*'], ['\.', '.*'], $pattern).'$/';

        return (bool) preg_match($regex, $key);
    }

    /**
     * Resolve all keys for a workspace.
     *
     * NOTE: Only called during prime, not normal reads.
     *
     * @param  object|null  $workspace  Workspace model instance or null for system scope
     * @return array<string, ConfigResult>
     */
    public function resolveAll(?object $workspace = null, string|Channel|null $channel = null): array
    {
        $results = [];

        // Query all keys from DB (only during prime)
        foreach (ConfigKey::all() as $key) {
            $results[$key->code] = $this->resolve($key->code, $workspace, $channel);
        }

        return $results;
    }

    /**
     * Resolve all keys in a category.
     *
     * NOTE: Only called during prime, not normal reads.
     *
     * @param  object|null  $workspace  Workspace model instance or null for system scope
     * @return array<string, ConfigResult>
     */
    public function resolveCategory(
        string $category,
        ?object $workspace = null,
        string|Channel|null $channel = null,
    ): array {
        $results = [];

        // Query keys by category from DB (only during prime)
        foreach (ConfigKey::where('category', $category)->get() as $key) {
            $results[$key->code] = $this->resolve($key->code, $workspace, $channel);
        }

        return $results;
    }

    /**
     * Build the profile chain for resolution.
     *
     * Returns profiles ordered from most specific (workspace) to least (system).
     * Chain: workspace → org → system
     *
     * @param  object|null  $workspace  Workspace model instance or null for system scope
     * @return Collection<int, ConfigProfile>
     */
    public function buildProfileChain(?object $workspace = null): Collection
    {
        $chain = new Collection;

        // Workspace profiles (most specific)
        if ($workspace !== null) {
            $workspaceProfiles = ConfigProfile::forScope(ScopeType::WORKSPACE, $workspace->id);
            $chain = $chain->merge($workspaceProfiles);

            // Org layer - workspace belongs to organisation
            $orgId = $this->resolveOrgId($workspace);
            if ($orgId !== null) {
                $orgProfiles = ConfigProfile::forScope(ScopeType::ORG, $orgId);
                $chain = $chain->merge($orgProfiles);
            }
        }

        // System profiles (least specific)
        $systemProfiles = ConfigProfile::forScope(ScopeType::SYSTEM, null);
        $chain = $chain->merge($systemProfiles);

        // Add parent profile inheritance
        $chain = $this->expandParentProfiles($chain);

        return $chain;
    }

    /**
     * Resolve organisation ID from workspace.
     *
     * Stub for now - will connect to Tenant module when org model exists.
     * Organisation = multi-workspace grouping (agency accounts, teams).
     *
     * @param  object|null  $workspace  Workspace model instance or null
     */
    protected function resolveOrgId(?object $workspace): ?int
    {
        if ($workspace === null) {
            return null;
        }

        // Workspace::organisation_id when model has org support
        // For now, return null (no org layer)
        return $workspace->organisation_id ?? null;
    }

    /**
     * Expand chain to include parent profiles.
     *
     * @param  Collection<int, ConfigProfile>  $chain
     * @return Collection<int, ConfigProfile>
     */
    protected function expandParentProfiles(Collection $chain): Collection
    {
        $expanded = new Collection;
        $seen = [];

        foreach ($chain as $profile) {
            $this->addProfileWithParents($profile, $expanded, $seen);
        }

        return $expanded;
    }

    /**
     * Add a profile and its parents to the chain.
     *
     * @param  Collection<int, ConfigProfile>  $chain
     * @param  array<int, bool>  $seen
     */
    protected function addProfileWithParents(ConfigProfile $profile, Collection $chain, array &$seen): void
    {
        if (isset($seen[$profile->id])) {
            return;
        }

        $seen[$profile->id] = true;
        $chain->push($profile);

        // Follow parent chain
        if ($profile->parent_profile_id !== null) {
            $parent = $profile->parent;

            if ($parent !== null) {
                $this->addProfileWithParents($parent, $chain, $seen);
            }
        }
    }

    /**
     * Check if a key prefix is configured.
     *
     * Optimised to use EXISTS query instead of resolving each key.
     *
     * @param  object|null  $workspace  Workspace model instance or null for system scope
     */
    public function isPrefixConfigured(
        string $prefix,
        ?object $workspace = null,
        string|Channel|null $channel = null,
    ): bool {
        // Get profile IDs for this workspace
        $profileChain = $this->buildProfileChain($workspace);
        $profileIds = $profileChain->pluck('id')->all();

        // Get channel IDs
        $channelChain = $this->buildChannelChain($channel, $workspace);
        $channelIds = $channelChain->map(fn ($c) => $c?->id)->all();
        $actualChannelIds = array_filter($channelIds, fn ($id) => $id !== null);

        // Single EXISTS query
        return ConfigValue::whereIn('profile_id', $profileIds)
            ->where(function ($query) use ($actualChannelIds) {
                $query->whereNull('channel_id');
                if (! empty($actualChannelIds)) {
                    $query->orWhereIn('channel_id', $actualChannelIds);
                }
            })
            ->whereHas('key', function ($query) use ($prefix) {
                $query->where('code', 'LIKE', "{$prefix}.%");
            })
            ->exists();
    }
}
