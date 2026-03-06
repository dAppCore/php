<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Storage;

use Closure;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Multi-tier cache store implementation.
 *
 * Implements a cascading cache strategy where values are checked in multiple
 * tiers from fastest to slowest. On a hit, values are optionally promoted
 * to faster tiers for subsequent access.
 *
 * ## Tier Order (default)
 *
 * 1. **Memory (array)**: In-process, request-scoped (TTL: 60s)
 * 2. **Redis**: Shared, distributed (TTL: 1 hour)
 * 3. **Database**: Persistent, durable (TTL: 24 hours)
 *
 * ## Read Behavior
 *
 * ```
 * get("user:123")
 *   |
 *   v
 * [Memory] -- miss --> [Redis] -- miss --> [Database]
 *                         |                    |
 *                        hit                  hit
 *                         |                    |
 *                         v                    v
 *                    return value         promote to Redis & Memory
 *                                         return value
 * ```
 *
 * ## Write Behavior
 *
 * Writes go to all enabled tiers with tier-specific TTLs:
 * - Memory tier: Short TTL (e.g., 60s)
 * - Redis tier: Medium TTL (e.g., 1 hour)
 * - Database tier: Long TTL (e.g., 24 hours)
 *
 * ## Configuration
 *
 * ```php
 * $tiered = new TieredCacheStore([
 *     TierConfiguration::memory(ttl: 60),
 *     TierConfiguration::redis(ttl: 3600),
 *     TierConfiguration::database(ttl: 86400),
 * ]);
 *
 * // Or configure via config/core.php
 * 'storage' => [
 *     'tiered_cache' => [
 *         'enabled' => true,
 *         'tiers' => [
 *             ['name' => 'memory', 'driver' => 'array', 'ttl' => 60],
 *             ['name' => 'redis', 'driver' => 'redis', 'ttl' => 3600],
 *             ['name' => 'database', 'driver' => 'database', 'ttl' => 86400],
 *         ],
 *     ],
 * ],
 * ```
 *
 * ## Metrics
 *
 * Integrates with StorageMetrics to track:
 * - Hits/misses per tier
 * - Promotion events
 * - Operation latencies
 *
 * @implements Store
 */
class TieredCacheStore implements Store
{
    /**
     * Sentinel value for cache misses (to distinguish null from not found).
     */
    private const MISS_SENTINEL = '__TIERED_CACHE_MISS__';

    /**
     * Configured cache tiers, sorted by priority.
     *
     * @var array<TierConfiguration>
     */
    protected array $tiers = [];

    /**
     * Resolved cache store instances per tier.
     *
     * @var array<string, CacheRepository>
     */
    protected array $stores = [];

    /**
     * Storage metrics collector.
     */
    protected ?StorageMetrics $metrics = null;

    /**
     * Whether tiered caching is enabled.
     */
    protected bool $enabled;

    /**
     * Whether to log operations.
     */
    protected bool $logEnabled;

    /**
     * Cache key prefix.
     */
    protected string $prefix;

    /**
     * Create a new tiered cache store.
     *
     * @param  array<TierConfiguration>  $tiers  Tier configurations
     * @param  string  $prefix  Cache key prefix
     */
    public function __construct(
        array $tiers = [],
        string $prefix = '',
    ) {
        $this->enabled = (bool) config('core.storage.tiered_cache.enabled', true);
        $this->logEnabled = (bool) config('core.storage.tiered_cache.log_enabled', false);
        $this->prefix = $prefix;

        if (empty($tiers)) {
            $tiers = $this->getDefaultTiers();
        }

        // Sort tiers by priority
        usort($tiers, fn (TierConfiguration $a, TierConfiguration $b) => $a->priority <=> $b->priority);

        // Filter to enabled tiers only
        $this->tiers = array_values(array_filter($tiers, fn (TierConfiguration $t) => $t->enabled));
    }

    /**
     * Get default tier configurations from config.
     *
     * @return array<TierConfiguration>
     */
    protected function getDefaultTiers(): array
    {
        $configTiers = config('core.storage.tiered_cache.tiers', []);

        if (! empty($configTiers)) {
            return array_map(
                fn (array $tier) => TierConfiguration::fromArray($tier),
                $configTiers
            );
        }

        // Default: memory -> redis -> database
        return [
            TierConfiguration::memory(ttl: 60),
            TierConfiguration::redis(ttl: 3600),
            TierConfiguration::database(ttl: 86400),
        ];
    }

    /**
     * Retrieve an item from the cache by key.
     *
     * Checks tiers in order from fastest to slowest. On a hit,
     * optionally promotes the value to faster tiers.
     *
     * @param  string|array  $key
     */
    public function get($key): mixed
    {
        if (! $this->enabled || empty($this->tiers)) {
            return $this->getFallbackStore()->get($this->prefix.$key);
        }

        $startTime = microtime(true);
        $prefixedKey = $this->prefix.$key;

        // Check each tier in order
        foreach ($this->tiers as $index => $tier) {
            $store = $this->getStoreForTier($tier);

            try {
                $value = $store->get($prefixedKey);

                if ($value !== null) {
                    $this->recordHit($tier->name, microtime(true) - $startTime);

                    // Promote to faster tiers
                    if ($index > 0) {
                        $this->promoteToFasterTiers($prefixedKey, $value, $index);
                    }

                    $this->log('debug', "Cache hit in tier: {$tier->name}", ['key' => $key]);

                    return $value;
                }

                $this->recordMiss($tier->name, microtime(true) - $startTime);
            } catch (\Throwable $e) {
                $this->recordError($tier->name, 'get', $e);
                $this->log('warning', "Tier {$tier->name} error on get", [
                    'key' => $key,
                    'error' => $e->getMessage(),
                ]);
                // Continue to next tier
            }
        }

        $this->log('debug', 'Cache miss in all tiers', ['key' => $key]);

        return null;
    }

    /**
     * Retrieve multiple items from the cache by key.
     */
    public function many(array $keys): array
    {
        $results = [];
        $prefixedKeys = array_map(fn ($k) => $this->prefix.$k, $keys);
        $keyMap = array_combine($prefixedKeys, $keys);

        if (! $this->enabled || empty($this->tiers)) {
            $values = $this->getFallbackStore()->many($prefixedKeys);
            foreach ($values as $prefixedKey => $value) {
                $results[$keyMap[$prefixedKey]] = $value;
            }

            return $results;
        }

        $missing = $prefixedKeys;
        $found = [];

        foreach ($this->tiers as $index => $tier) {
            if (empty($missing)) {
                break;
            }

            $store = $this->getStoreForTier($tier);

            try {
                $values = $store->many($missing);

                foreach ($values as $prefixedKey => $value) {
                    if ($value !== null) {
                        $found[$prefixedKey] = [
                            'value' => $value,
                            'tier_index' => $index,
                        ];
                        $missing = array_diff($missing, [$prefixedKey]);
                    }
                }
            } catch (\Throwable $e) {
                $this->recordError($tier->name, 'many', $e);
                // Continue to next tier
            }
        }

        // Promote values to faster tiers
        foreach ($found as $prefixedKey => $data) {
            if ($data['tier_index'] > 0) {
                $this->promoteToFasterTiers($prefixedKey, $data['value'], $data['tier_index']);
            }
            $results[$keyMap[$prefixedKey]] = $data['value'];
        }

        // Fill in nulls for missing keys
        foreach ($missing as $prefixedKey) {
            $results[$keyMap[$prefixedKey]] = null;
        }

        return $results;
    }

    /**
     * Store an item in the cache for a given number of seconds.
     *
     * Writes to all enabled tiers with tier-specific TTLs.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @param  int  $seconds  Base TTL (each tier may adjust)
     */
    public function put($key, $value, $seconds): bool
    {
        $prefixedKey = $this->prefix.$key;

        if (! $this->enabled || empty($this->tiers)) {
            return $this->getFallbackStore()->put($prefixedKey, $value, $seconds);
        }

        $success = true;
        $startTime = microtime(true);

        foreach ($this->tiers as $tier) {
            $store = $this->getStoreForTier($tier);
            $tierTtl = $this->calculateTierTtl($tier, $seconds);

            try {
                if (! $store->put($prefixedKey, $value, $tierTtl)) {
                    $success = false;
                }
                $this->recordWrite($tier->name, microtime(true) - $startTime);
            } catch (\Throwable $e) {
                $this->recordError($tier->name, 'put', $e);
                $success = false;
                // Continue to other tiers
            }
        }

        return $success;
    }

    /**
     * Store multiple items in the cache for a given number of seconds.
     *
     * @param  int  $seconds
     */
    public function putMany(array $values, $seconds): bool
    {
        $prefixedValues = [];
        foreach ($values as $key => $value) {
            $prefixedValues[$this->prefix.$key] = $value;
        }

        if (! $this->enabled || empty($this->tiers)) {
            return $this->getFallbackStore()->putMany($prefixedValues, $seconds);
        }

        $success = true;

        foreach ($this->tiers as $tier) {
            $store = $this->getStoreForTier($tier);
            $tierTtl = $this->calculateTierTtl($tier, $seconds);

            try {
                if (! $store->putMany($prefixedValues, $tierTtl)) {
                    $success = false;
                }
            } catch (\Throwable $e) {
                $this->recordError($tier->name, 'putMany', $e);
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Increment the value of an item in the cache.
     *
     * @param  string  $key
     * @param  mixed  $value
     */
    public function increment($key, $value = 1): int|bool
    {
        $prefixedKey = $this->prefix.$key;

        if (! $this->enabled || empty($this->tiers)) {
            return $this->getFallbackStore()->increment($prefixedKey, $value);
        }

        // For counters, use the slowest (most authoritative) tier as source
        $authoritativeTier = end($this->tiers);
        $store = $this->getStoreForTier($authoritativeTier);

        try {
            $result = $store->increment($prefixedKey, $value);

            // Propagate to faster tiers
            if ($result !== false) {
                $this->propagateToFasterTiers($prefixedKey, $result, count($this->tiers) - 1);
            }

            return $result;
        } catch (\Throwable $e) {
            $this->recordError($authoritativeTier->name, 'increment', $e);

            return false;
        }
    }

    /**
     * Decrement the value of an item in the cache.
     *
     * @param  string  $key
     * @param  mixed  $value
     */
    public function decrement($key, $value = 1): int|bool
    {
        $prefixedKey = $this->prefix.$key;

        if (! $this->enabled || empty($this->tiers)) {
            return $this->getFallbackStore()->decrement($prefixedKey, $value);
        }

        // For counters, use the slowest (most authoritative) tier as source
        $authoritativeTier = end($this->tiers);
        $store = $this->getStoreForTier($authoritativeTier);

        try {
            $result = $store->decrement($prefixedKey, $value);

            // Propagate to faster tiers
            if ($result !== false) {
                $this->propagateToFasterTiers($prefixedKey, $result, count($this->tiers) - 1);
            }

            return $result;
        } catch (\Throwable $e) {
            $this->recordError($authoritativeTier->name, 'decrement', $e);

            return false;
        }
    }

    /**
     * Store an item in the cache indefinitely.
     *
     * @param  string  $key
     * @param  mixed  $value
     */
    public function forever($key, $value): bool
    {
        $prefixedKey = $this->prefix.$key;

        if (! $this->enabled || empty($this->tiers)) {
            return $this->getFallbackStore()->forever($prefixedKey, $value);
        }

        $success = true;

        foreach ($this->tiers as $tier) {
            $store = $this->getStoreForTier($tier);

            try {
                if (! $store->forever($prefixedKey, $value)) {
                    $success = false;
                }
            } catch (\Throwable $e) {
                $this->recordError($tier->name, 'forever', $e);
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Remove an item from the cache.
     *
     * Removes from all tiers.
     *
     * @param  string  $key
     */
    public function forget($key): bool
    {
        $prefixedKey = $this->prefix.$key;

        if (! $this->enabled || empty($this->tiers)) {
            return $this->getFallbackStore()->forget($prefixedKey);
        }

        $success = true;

        foreach ($this->tiers as $tier) {
            $store = $this->getStoreForTier($tier);

            try {
                if (! $store->forget($prefixedKey)) {
                    $success = false;
                }
                $this->recordDelete($tier->name);
            } catch (\Throwable $e) {
                $this->recordError($tier->name, 'forget', $e);
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Remove all items from the cache.
     *
     * Flushes all tiers.
     */
    public function flush(): bool
    {
        if (! $this->enabled || empty($this->tiers)) {
            return $this->getFallbackStore()->flush();
        }

        $success = true;

        foreach ($this->tiers as $tier) {
            $store = $this->getStoreForTier($tier);

            try {
                if (! $store->flush()) {
                    $success = false;
                }
            } catch (\Throwable $e) {
                $this->recordError($tier->name, 'flush', $e);
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Get the cache key prefix.
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * Get or set the value of an item.
     *
     * Implements the "cache-aside" pattern with automatic promotion.
     *
     * @param  int  $ttl  TTL in seconds
     * @param  Closure  $callback  Callback to generate value if missing
     */
    public function remember(string $key, int $ttl, Closure $callback): mixed
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();

        $this->put($key, $value, $ttl);

        return $value;
    }

    /**
     * Get or set the value of an item forever.
     */
    public function rememberForever(string $key, Closure $callback): mixed
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();

        $this->forever($key, $value);

        return $value;
    }

    /**
     * Get tier statistics.
     *
     * @return array<string, array{name: string, driver: string, ttl: int, enabled: bool}>
     */
    public function getTierStats(): array
    {
        $stats = [];

        foreach ($this->tiers as $tier) {
            $stats[$tier->name] = [
                'name' => $tier->name,
                'driver' => $tier->driver,
                'ttl' => $tier->ttl,
                'promoteOnHit' => $tier->promoteOnHit,
                'priority' => $tier->priority,
                'enabled' => $tier->enabled,
            ];
        }

        return $stats;
    }

    /**
     * Get the configured tiers.
     *
     * @return array<TierConfiguration>
     */
    public function getTiers(): array
    {
        return $this->tiers;
    }

    /**
     * Add a tier dynamically.
     */
    public function addTier(TierConfiguration $tier): static
    {
        $this->tiers[] = $tier;
        usort($this->tiers, fn (TierConfiguration $a, TierConfiguration $b) => $a->priority <=> $b->priority);

        return $this;
    }

    /**
     * Remove a tier by name.
     */
    public function removeTier(string $name): static
    {
        $this->tiers = array_values(array_filter(
            $this->tiers,
            fn (TierConfiguration $t) => $t->name !== $name
        ));

        unset($this->stores[$name]);

        return $this;
    }

    /**
     * Enable or disable tiered caching.
     */
    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;

        return $this;
    }

    /**
     * Check if tiered caching is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Promote a value to faster tiers (lower indexes).
     *
     * @param  string  $key  Prefixed cache key
     * @param  mixed  $value  Value to promote
     * @param  int  $foundAtIndex  Index where value was found
     */
    protected function promoteToFasterTiers(string $key, mixed $value, int $foundAtIndex): void
    {
        for ($i = 0; $i < $foundAtIndex; $i++) {
            $tier = $this->tiers[$i];

            if (! $tier->promoteOnHit) {
                continue;
            }

            try {
                $store = $this->getStoreForTier($tier);
                $store->put($key, $value, $tier->ttl);

                $this->log('debug', "Promoted to tier: {$tier->name}", ['key' => $key]);
                $this->getMetrics()?->increment('tiered', 'promotions');
            } catch (\Throwable $e) {
                $this->recordError($tier->name, 'promote', $e);
                // Continue promoting to other tiers
            }
        }
    }

    /**
     * Propagate a counter value to faster tiers.
     *
     * @param  string  $key  Prefixed cache key
     * @param  int  $value  Counter value
     * @param  int  $sourceIndex  Index of authoritative tier
     */
    protected function propagateToFasterTiers(string $key, int $value, int $sourceIndex): void
    {
        for ($i = 0; $i < $sourceIndex; $i++) {
            $tier = $this->tiers[$i];

            try {
                $store = $this->getStoreForTier($tier);
                $store->put($key, $value, $tier->ttl);
            } catch (\Throwable $e) {
                $this->recordError($tier->name, 'propagate', $e);
            }
        }
    }

    /**
     * Calculate tier-specific TTL.
     *
     * Uses the smaller of the requested TTL and the tier's configured TTL.
     */
    protected function calculateTierTtl(TierConfiguration $tier, int $requestedTtl): int
    {
        return min($tier->ttl, $requestedTtl);
    }

    /**
     * Get the cache store for a tier.
     */
    protected function getStoreForTier(TierConfiguration $tier): CacheRepository
    {
        if (! isset($this->stores[$tier->name])) {
            $this->stores[$tier->name] = Cache::store($tier->driver);
        }

        return $this->stores[$tier->name];
    }

    /**
     * Get the fallback store when tiered caching is disabled.
     */
    protected function getFallbackStore(): CacheRepository
    {
        return Cache::store();
    }

    /**
     * Get the metrics collector.
     */
    protected function getMetrics(): ?StorageMetrics
    {
        if ($this->metrics === null && app()->bound(StorageMetrics::class)) {
            $this->metrics = app(StorageMetrics::class);
        }

        return $this->metrics;
    }

    /**
     * Record a cache hit.
     */
    protected function recordHit(string $tier, float $duration): void
    {
        $this->getMetrics()?->recordHit($tier, $duration);
    }

    /**
     * Record a cache miss.
     */
    protected function recordMiss(string $tier, float $duration): void
    {
        $this->getMetrics()?->recordMiss($tier, $duration);
    }

    /**
     * Record a cache write.
     */
    protected function recordWrite(string $tier, float $duration): void
    {
        $this->getMetrics()?->recordWrite($tier, $duration);
    }

    /**
     * Record a cache delete.
     */
    protected function recordDelete(string $tier): void
    {
        $this->getMetrics()?->recordDelete($tier, 0.0);
    }

    /**
     * Record an error.
     */
    protected function recordError(string $tier, string $operation, \Throwable $e): void
    {
        $this->getMetrics()?->recordError($tier, $operation, $e);
    }

    /**
     * Log a message if logging is enabled.
     *
     * @param  array<string, mixed>  $context
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        if (! $this->logEnabled) {
            return;
        }

        Log::log($level, "[TieredCache] {$message}", $context);
    }
}
