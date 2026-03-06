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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Cache warming mechanism for pre-populating cache.
 *
 * Provides a strategy for warming cache with frequently accessed data
 * during off-peak times or after deployments to prevent cache stampedes.
 *
 * ## What is Cache Warming?
 *
 * Cache warming is the process of pre-populating the cache with data
 * before it's needed, rather than waiting for the first request.
 * This prevents the "cold cache" problem where the first users after
 * a deployment or cache flush experience slow response times.
 *
 * ## Usage
 *
 * ```php
 * $warmer = new CacheWarmer();
 *
 * // Register items to warm
 * $warmer->register('user_settings', fn() => UserSettings::all()->keyBy('key'));
 * $warmer->register('site_config', fn() => Config::pluck('value', 'key'), ttl: 3600);
 *
 * // Warm all registered items
 * $results = $warmer->warmAll();
 *
 * // Warm specific item
 * $warmer->warm('user_settings');
 *
 * // Schedule warming (typically in a scheduled command)
 * $warmer->warmStale(); // Only warms items that are missing or expired
 * ```
 *
 * ## Batch Warming
 *
 * For large datasets, use batch warming to prevent memory issues:
 *
 * ```php
 * $warmer->registerBatch('products', function(int $offset, int $limit) {
 *     return Product::skip($offset)->take($limit)->get();
 * }, batchSize: 100, totalItems: 10000);
 * ```
 *
 * ## Configuration
 *
 * - `core.storage.cache_warming.enabled`: Enable/disable warming (default: true)
 * - `core.storage.cache_warming.default_ttl`: Default TTL in seconds (default: 3600)
 * - `core.storage.cache_warming.log_enabled`: Log warming operations (default: true)
 * - `core.storage.cache_warming.concurrency`: Max concurrent warming operations (default: 5)
 */
class CacheWarmer
{
    /**
     * Registered warming items.
     *
     * @var array<string, array{callback: Closure, ttl: int, tags: array, priority: int}>
     */
    protected array $items = [];

    /**
     * Registered batch warming items.
     *
     * @var array<string, array{callback: Closure, batchSize: int, totalItems: int, ttl: int, tags: array}>
     */
    protected array $batchItems = [];

    /**
     * Warming results from the last operation.
     *
     * @var array<string, array{status: string, duration: float, error?: string}>
     */
    protected array $lastResults = [];

    /**
     * Whether cache warming is enabled.
     */
    protected bool $enabled;

    /**
     * Default TTL for cached items.
     */
    protected int $defaultTtl;

    /**
     * Whether to log warming operations.
     */
    protected bool $logEnabled;

    /**
     * Maximum concurrent warming operations.
     */
    protected int $concurrency;

    /**
     * Cache store to use.
     */
    protected ?CacheRepository $store = null;

    public function __construct()
    {
        $this->enabled = (bool) config('core.storage.cache_warming.enabled', true);
        $this->defaultTtl = (int) config('core.storage.cache_warming.default_ttl', 3600);
        $this->logEnabled = (bool) config('core.storage.cache_warming.log_enabled', true);
        $this->concurrency = (int) config('core.storage.cache_warming.concurrency', 5);
    }

    /**
     * Register an item for cache warming.
     *
     * @param  string  $key  Cache key
     * @param  Closure  $callback  Callback that returns the data to cache
     * @param  int|null  $ttl  Time-to-live in seconds (null uses default)
     * @param  array<string>  $tags  Cache tags (if supported by driver)
     * @param  int  $priority  Warming priority (lower = higher priority)
     */
    public function register(
        string $key,
        Closure $callback,
        ?int $ttl = null,
        array $tags = [],
        int $priority = 50
    ): static {
        $this->items[$key] = [
            'callback' => $callback,
            'ttl' => $ttl ?? $this->defaultTtl,
            'tags' => $tags,
            'priority' => $priority,
        ];

        return $this;
    }

    /**
     * Register a batch item for cache warming.
     *
     * Use for large datasets that should be warmed in chunks.
     *
     * @param  string  $keyPrefix  Cache key prefix (actual keys will be {prefix}:{offset})
     * @param  Closure  $callback  Callback(int $offset, int $limit) that returns batch data
     * @param  int  $batchSize  Number of items per batch
     * @param  int  $totalItems  Total number of items (for progress tracking)
     * @param  int|null  $ttl  Time-to-live in seconds
     * @param  array<string>  $tags  Cache tags
     */
    public function registerBatch(
        string $keyPrefix,
        Closure $callback,
        int $batchSize = 100,
        int $totalItems = 0,
        ?int $ttl = null,
        array $tags = []
    ): static {
        $this->batchItems[$keyPrefix] = [
            'callback' => $callback,
            'batchSize' => $batchSize,
            'totalItems' => $totalItems,
            'ttl' => $ttl ?? $this->defaultTtl,
            'tags' => $tags,
        ];

        return $this;
    }

    /**
     * Unregister an item from cache warming.
     */
    public function unregister(string $key): static
    {
        unset($this->items[$key], $this->batchItems[$key]);

        return $this;
    }

    /**
     * Warm all registered items.
     *
     * @return array<string, array{status: string, duration: float, error?: string}>
     */
    public function warmAll(): array
    {
        if (! $this->enabled) {
            return ['_disabled' => ['status' => 'skipped', 'duration' => 0.0]];
        }

        $this->lastResults = [];
        $startTime = microtime(true);

        $this->log('info', 'Starting cache warming', [
            'items' => count($this->items),
            'batch_items' => count($this->batchItems),
        ]);

        // Sort by priority
        $sortedItems = $this->items;
        uasort($sortedItems, fn ($a, $b) => $a['priority'] <=> $b['priority']);

        // Warm regular items
        foreach ($sortedItems as $key => $item) {
            $this->warmItem($key, $item);
        }

        // Warm batch items
        foreach ($this->batchItems as $keyPrefix => $item) {
            $this->warmBatchItem($keyPrefix, $item);
        }

        $totalDuration = microtime(true) - $startTime;

        $this->log('info', 'Cache warming completed', [
            'total_duration' => round($totalDuration, 3),
            'items_warmed' => count($this->lastResults),
            'successes' => count(array_filter($this->lastResults, fn ($r) => $r['status'] === 'success')),
            'failures' => count(array_filter($this->lastResults, fn ($r) => $r['status'] === 'failed')),
        ]);

        return $this->lastResults;
    }

    /**
     * Warm a specific item by key.
     */
    public function warm(string $key): bool
    {
        if (! $this->enabled) {
            return false;
        }

        if (isset($this->items[$key])) {
            $result = $this->warmItem($key, $this->items[$key]);

            return $result['status'] === 'success';
        }

        if (isset($this->batchItems[$key])) {
            $this->warmBatchItem($key, $this->batchItems[$key]);

            return true;
        }

        return false;
    }

    /**
     * Warm only stale (missing or expired) items.
     *
     * More efficient than warmAll() as it skips items that are still cached.
     *
     * @return array<string, array{status: string, duration: float, error?: string}>
     */
    public function warmStale(): array
    {
        if (! $this->enabled) {
            return ['_disabled' => ['status' => 'skipped', 'duration' => 0.0]];
        }

        $this->lastResults = [];
        $cache = $this->getStore();

        foreach ($this->items as $key => $item) {
            if (! $cache->has($key)) {
                $this->warmItem($key, $item);
            } else {
                $this->lastResults[$key] = ['status' => 'exists', 'duration' => 0.0];
            }
        }

        return $this->lastResults;
    }

    /**
     * Check if an item is warm (exists in cache).
     */
    public function isWarm(string $key): bool
    {
        return $this->getStore()->has($key);
    }

    /**
     * Get the warming status of all registered items.
     *
     * @return array<string, array{registered: bool, cached: bool, ttl: int}>
     */
    public function getStatus(): array
    {
        $status = [];
        $cache = $this->getStore();

        foreach ($this->items as $key => $item) {
            $status[$key] = [
                'registered' => true,
                'cached' => $cache->has($key),
                'ttl' => $item['ttl'],
                'priority' => $item['priority'],
                'type' => 'single',
            ];
        }

        foreach ($this->batchItems as $keyPrefix => $item) {
            $status[$keyPrefix] = [
                'registered' => true,
                'cached' => null, // Batch items have multiple keys
                'ttl' => $item['ttl'],
                'batch_size' => $item['batchSize'],
                'total_items' => $item['totalItems'],
                'type' => 'batch',
            ];
        }

        return $status;
    }

    /**
     * Get the last warming results.
     *
     * @return array<string, array{status: string, duration: float, error?: string}>
     */
    public function getLastResults(): array
    {
        return $this->lastResults;
    }

    /**
     * Invalidate (remove) a warmed item from cache.
     */
    public function invalidate(string $key): bool
    {
        return $this->getStore()->forget($key);
    }

    /**
     * Invalidate all registered items from cache.
     */
    public function invalidateAll(): int
    {
        $count = 0;
        $cache = $this->getStore();

        foreach (array_keys($this->items) as $key) {
            if ($cache->forget($key)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Set the cache store to use.
     */
    public function useStore(string $store): static
    {
        $this->store = Cache::store($store);

        return $this;
    }

    /**
     * Enable or disable cache warming.
     */
    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;

        return $this;
    }

    /**
     * Set the default TTL.
     */
    public function setDefaultTtl(int $seconds): static
    {
        $this->defaultTtl = $seconds;

        return $this;
    }

    /**
     * Get all registered item keys.
     *
     * @return array<string>
     */
    public function getRegisteredKeys(): array
    {
        return array_merge(
            array_keys($this->items),
            array_keys($this->batchItems)
        );
    }

    /**
     * Check if an item is registered.
     */
    public function isRegistered(string $key): bool
    {
        return isset($this->items[$key]) || isset($this->batchItems[$key]);
    }

    /**
     * Get warming statistics summary.
     *
     * @return array{total_registered: int, total_cached: int, cache_rate: float}
     */
    public function getStats(): array
    {
        $total = count($this->items);
        $cached = 0;
        $cache = $this->getStore();

        foreach (array_keys($this->items) as $key) {
            if ($cache->has($key)) {
                $cached++;
            }
        }

        return [
            'total_registered' => $total,
            'total_cached' => $cached,
            'cache_rate' => $total > 0 ? round($cached / $total * 100, 2) : 0.0,
            'batch_items' => count($this->batchItems),
        ];
    }

    /**
     * Warm a single item.
     *
     * @param  array{callback: Closure, ttl: int, tags: array, priority: int}  $item
     * @return array{status: string, duration: float, error?: string}
     */
    protected function warmItem(string $key, array $item): array
    {
        $startTime = microtime(true);

        try {
            $data = ($item['callback'])();
            $cache = $this->getStore();

            if (! empty($item['tags']) && method_exists($cache, 'tags')) {
                $cache->tags($item['tags'])->put($key, $data, $item['ttl']);
            } else {
                $cache->put($key, $data, $item['ttl']);
            }

            $duration = microtime(true) - $startTime;

            $this->lastResults[$key] = [
                'status' => 'success',
                'duration' => round($duration, 4),
            ];

            $this->log('debug', "Warmed cache key: {$key}", [
                'duration' => round($duration, 4),
                'ttl' => $item['ttl'],
            ]);

            return $this->lastResults[$key];
        } catch (\Throwable $e) {
            $duration = microtime(true) - $startTime;

            $this->lastResults[$key] = [
                'status' => 'failed',
                'duration' => round($duration, 4),
                'error' => $e->getMessage(),
            ];

            $this->log('error', "Failed to warm cache key: {$key}", [
                'error' => $e->getMessage(),
                'duration' => round($duration, 4),
            ]);

            return $this->lastResults[$key];
        }
    }

    /**
     * Warm a batch item.
     *
     * @param  array{callback: Closure, batchSize: int, totalItems: int, ttl: int, tags: array}  $item
     */
    protected function warmBatchItem(string $keyPrefix, array $item): void
    {
        $offset = 0;
        $batchNumber = 0;

        while (true) {
            $batchKey = "{$keyPrefix}:{$offset}";
            $startTime = microtime(true);

            try {
                $data = ($item['callback'])($offset, $item['batchSize']);

                // Empty result means we've processed all items
                if (empty($data)) {
                    break;
                }

                // Handle both arrays and collections
                $count = is_countable($data) ? count($data) : 0;

                if ($count === 0) {
                    break;
                }

                $cache = $this->getStore();

                if (! empty($item['tags']) && method_exists($cache, 'tags')) {
                    $cache->tags($item['tags'])->put($batchKey, $data, $item['ttl']);
                } else {
                    $cache->put($batchKey, $data, $item['ttl']);
                }

                $duration = microtime(true) - $startTime;

                $this->lastResults[$batchKey] = [
                    'status' => 'success',
                    'duration' => round($duration, 4),
                    'items' => $count,
                ];

                $offset += $item['batchSize'];
                $batchNumber++;

                // Safety check to prevent infinite loops
                if ($item['totalItems'] > 0 && $offset >= $item['totalItems']) {
                    break;
                }

                // Also break if we got fewer items than batch size (end of data)
                if ($count < $item['batchSize']) {
                    break;
                }
            } catch (\Throwable $e) {
                $duration = microtime(true) - $startTime;

                $this->lastResults[$batchKey] = [
                    'status' => 'failed',
                    'duration' => round($duration, 4),
                    'error' => $e->getMessage(),
                ];

                $this->log('error', "Failed to warm batch key: {$batchKey}", [
                    'error' => $e->getMessage(),
                ]);

                // Continue with next batch even if one fails
                $offset += $item['batchSize'];
                $batchNumber++;

                if ($item['totalItems'] > 0 && $offset >= $item['totalItems']) {
                    break;
                }
            }
        }

        $this->log('debug', "Warmed batch: {$keyPrefix}", [
            'batches' => $batchNumber,
            'total_offset' => $offset,
        ]);
    }

    /**
     * Get the cache store.
     */
    protected function getStore(): CacheRepository
    {
        return $this->store ?? Cache::store();
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

        Log::log($level, "[CacheWarmer] {$message}", $context);
    }
}
