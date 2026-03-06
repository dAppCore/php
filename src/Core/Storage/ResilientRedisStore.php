<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Storage;

use Core\Storage\Events\RedisFallbackActivated;
use Illuminate\Cache\DatabaseStore;
use Illuminate\Cache\RedisStore;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Log;

/**
 * Redis cache store with automatic database fallback and circuit breaker.
 *
 * Wraps Redis operations in try-catch. If Redis fails repeatedly,
 * the circuit breaker opens to prevent cascading failures. Operations
 * fall back to database store when Redis is unavailable.
 *
 * ## Circuit Breaker
 *
 * The circuit breaker prevents thundering herd problems when Redis
 * goes down by stopping requests to Redis until it recovers:
 *
 * - **Closed**: Normal operation, requests go to Redis
 * - **Open**: Redis failing, skip Redis and use fallback directly
 * - **Half-Open**: Testing if Redis has recovered
 *
 * ## Metrics
 *
 * Storage metrics are collected for monitoring cache health:
 * - Hit/miss rates
 * - Operation latencies
 * - Fallback activations
 * - Circuit breaker state changes
 */
class ResilientRedisStore extends RedisStore
{
    protected ?DatabaseStore $fallbackStore = null;

    protected bool $fallbackActivated = false;

    protected ?CircuitBreaker $circuitBreaker = null;

    protected ?StorageMetrics $metrics = null;

    /**
     * Get the fallback database store.
     */
    protected function getFallbackStore(): DatabaseStore
    {
        if ($this->fallbackStore === null) {
            $this->fallbackStore = new DatabaseStore(
                app('db')->connection(),
                'cache',
                app('config')->get('cache.prefix', '')
            );
        }

        return $this->fallbackStore;
    }

    /**
     * Get the circuit breaker instance.
     */
    protected function getCircuitBreaker(): CircuitBreaker
    {
        if ($this->circuitBreaker === null) {
            $this->circuitBreaker = new CircuitBreaker('redis');
        }

        return $this->circuitBreaker;
    }

    /**
     * Get the metrics collector instance.
     */
    protected function getMetrics(): StorageMetrics
    {
        if ($this->metrics === null) {
            $this->metrics = app(StorageMetrics::class);
        }

        return $this->metrics;
    }

    /**
     * Check if circuit breaker is enabled.
     */
    protected function isCircuitBreakerEnabled(): bool
    {
        return (bool) config('core.storage.circuit_breaker.enabled', true);
    }

    /**
     * Handle Redis failure by logging and optionally dispatching an event.
     *
     * @throws \Throwable When silent_fallback is disabled
     */
    protected function handleRedisFailure(\Throwable $e, string $operation = 'unknown'): void
    {
        // Record failure with circuit breaker
        if ($this->isCircuitBreakerEnabled()) {
            $this->getCircuitBreaker()->recordFailure();
        }

        // Record error in metrics
        $this->getMetrics()->recordError('redis', $operation, $e);

        $silentFallback = config('core.storage.silent_fallback', true);

        if (! $silentFallback) {
            throw $e;
        }

        $this->logFallback($e);
        $this->dispatchFallbackEvent($e);
    }

    /**
     * Record a successful Redis operation.
     */
    protected function recordSuccess(string $operation, float $startTime, bool $isHit = true): void
    {
        // Record success with circuit breaker
        if ($this->isCircuitBreakerEnabled()) {
            $this->getCircuitBreaker()->recordSuccess();
        }

        // Record metrics
        $duration = microtime(true) - $startTime;
        $metrics = $this->getMetrics();

        if ($isHit) {
            $metrics->recordHit('redis', $duration);
        } else {
            $metrics->recordMiss('redis', $duration);
        }
    }

    /**
     * Check if Redis should be skipped due to circuit breaker.
     */
    protected function shouldSkipRedis(): bool
    {
        if (! $this->isCircuitBreakerEnabled()) {
            return false;
        }

        return ! $this->getCircuitBreaker()->isAvailable();
    }

    /**
     * Log the fallback (once per request).
     */
    protected function logFallback(\Throwable $e): void
    {
        if ($this->fallbackActivated) {
            return;
        }

        $logLevel = config('core.storage.fallback_log_level', 'warning');

        Log::log($logLevel, '[Cache] Redis unavailable, using database fallback', [
            'error' => $e->getMessage(),
            'exception_class' => get_class($e),
        ]);
    }

    /**
     * Dispatch the fallback event for monitoring/alerting (once per request).
     */
    protected function dispatchFallbackEvent(\Throwable $e): void
    {
        if ($this->fallbackActivated) {
            return;
        }

        $this->fallbackActivated = true;

        if (! config('core.storage.dispatch_fallback_events', true)) {
            return;
        }

        $dispatcher = app(Dispatcher::class);
        $dispatcher->dispatch(new RedisFallbackActivated(
            context: 'cache_operation',
            errorMessage: $e->getMessage(),
            fallbackDriver: 'database'
        ));
    }

    /**
     * Retrieve an item from the cache by key.
     */
    public function get($key): mixed
    {
        // Skip Redis if circuit breaker is open
        if ($this->shouldSkipRedis()) {
            $this->getMetrics()->recordFallbackActivation('redis', 'circuit_open');

            return $this->getFallbackStore()->get($key);
        }

        $startTime = microtime(true);

        try {
            $result = parent::get($key);
            $this->recordSuccess('get', $startTime, $result !== null);

            return $result;
        } catch (\Throwable $e) {
            $this->handleRedisFailure($e, 'get');

            return $this->getFallbackStore()->get($key);
        }
    }

    /**
     * Retrieve multiple items from the cache by key.
     */
    public function many(array $keys): array
    {
        if ($this->shouldSkipRedis()) {
            $this->getMetrics()->recordFallbackActivation('redis', 'circuit_open');

            return $this->getFallbackStore()->many($keys);
        }

        $startTime = microtime(true);

        try {
            $result = parent::many($keys);
            $this->recordSuccess('many', $startTime);

            return $result;
        } catch (\Throwable $e) {
            $this->handleRedisFailure($e, 'many');

            return $this->getFallbackStore()->many($keys);
        }
    }

    /**
     * Store an item in the cache for a given number of seconds.
     */
    public function put($key, $value, $seconds): bool
    {
        if ($this->shouldSkipRedis()) {
            $this->getMetrics()->recordFallbackActivation('redis', 'circuit_open');

            return $this->getFallbackStore()->put($key, $value, $seconds);
        }

        $startTime = microtime(true);

        try {
            $result = parent::put($key, $value, $seconds);
            $this->getCircuitBreaker()->recordSuccess();
            $this->getMetrics()->recordWrite('redis', microtime(true) - $startTime);

            return $result;
        } catch (\Throwable $e) {
            $this->handleRedisFailure($e, 'put');

            return $this->getFallbackStore()->put($key, $value, $seconds);
        }
    }

    /**
     * Store multiple items in the cache for a given number of seconds.
     */
    public function putMany(array $values, $seconds): bool
    {
        if ($this->shouldSkipRedis()) {
            $this->getMetrics()->recordFallbackActivation('redis', 'circuit_open');

            return $this->getFallbackStore()->putMany($values, $seconds);
        }

        $startTime = microtime(true);

        try {
            $result = parent::putMany($values, $seconds);
            $this->getCircuitBreaker()->recordSuccess();
            $this->getMetrics()->recordWrite('redis', microtime(true) - $startTime);

            return $result;
        } catch (\Throwable $e) {
            $this->handleRedisFailure($e, 'putMany');

            return $this->getFallbackStore()->putMany($values, $seconds);
        }
    }

    /**
     * Increment the value of an item in the cache.
     */
    public function increment($key, $value = 1): int|bool
    {
        if ($this->shouldSkipRedis()) {
            $this->getMetrics()->recordFallbackActivation('redis', 'circuit_open');

            return $this->getFallbackStore()->increment($key, $value);
        }

        $startTime = microtime(true);

        try {
            $result = parent::increment($key, $value);
            $this->getCircuitBreaker()->recordSuccess();
            $this->getMetrics()->recordWrite('redis', microtime(true) - $startTime);

            return $result;
        } catch (\Throwable $e) {
            $this->handleRedisFailure($e, 'increment');

            return $this->getFallbackStore()->increment($key, $value);
        }
    }

    /**
     * Decrement the value of an item in the cache.
     */
    public function decrement($key, $value = 1): int|bool
    {
        if ($this->shouldSkipRedis()) {
            $this->getMetrics()->recordFallbackActivation('redis', 'circuit_open');

            return $this->getFallbackStore()->decrement($key, $value);
        }

        $startTime = microtime(true);

        try {
            $result = parent::decrement($key, $value);
            $this->getCircuitBreaker()->recordSuccess();
            $this->getMetrics()->recordWrite('redis', microtime(true) - $startTime);

            return $result;
        } catch (\Throwable $e) {
            $this->handleRedisFailure($e, 'decrement');

            return $this->getFallbackStore()->decrement($key, $value);
        }
    }

    /**
     * Store an item in the cache indefinitely.
     */
    public function forever($key, $value): bool
    {
        if ($this->shouldSkipRedis()) {
            $this->getMetrics()->recordFallbackActivation('redis', 'circuit_open');

            return $this->getFallbackStore()->forever($key, $value);
        }

        $startTime = microtime(true);

        try {
            $result = parent::forever($key, $value);
            $this->getCircuitBreaker()->recordSuccess();
            $this->getMetrics()->recordWrite('redis', microtime(true) - $startTime);

            return $result;
        } catch (\Throwable $e) {
            $this->handleRedisFailure($e, 'forever');

            return $this->getFallbackStore()->forever($key, $value);
        }
    }

    /**
     * Remove an item from the cache.
     */
    public function forget($key): bool
    {
        if ($this->shouldSkipRedis()) {
            $this->getMetrics()->recordFallbackActivation('redis', 'circuit_open');

            return $this->getFallbackStore()->forget($key);
        }

        $startTime = microtime(true);

        try {
            $result = parent::forget($key);
            $this->getCircuitBreaker()->recordSuccess();
            $this->getMetrics()->recordDelete('redis', microtime(true) - $startTime);

            return $result;
        } catch (\Throwable $e) {
            $this->handleRedisFailure($e, 'forget');

            return $this->getFallbackStore()->forget($key);
        }
    }

    /**
     * Remove all items from the cache.
     */
    public function flush(): bool
    {
        if ($this->shouldSkipRedis()) {
            $this->getMetrics()->recordFallbackActivation('redis', 'circuit_open');

            return $this->getFallbackStore()->flush();
        }

        $startTime = microtime(true);

        try {
            $result = parent::flush();
            $this->getCircuitBreaker()->recordSuccess();
            $this->getMetrics()->recordDelete('redis', microtime(true) - $startTime);

            return $result;
        } catch (\Throwable $e) {
            $this->handleRedisFailure($e, 'flush');

            return $this->getFallbackStore()->flush();
        }
    }

    /**
     * Get the circuit breaker statistics.
     *
     * @return array<string, mixed>
     */
    public function getCircuitBreakerStats(): array
    {
        return $this->getCircuitBreaker()->getStats();
    }

    /**
     * Reset the circuit breaker to closed state.
     */
    public function resetCircuitBreaker(): void
    {
        $this->getCircuitBreaker()->reset();
    }

    /**
     * Get storage metrics.
     *
     * @return array<string, mixed>
     */
    public function getStorageMetrics(): array
    {
        return $this->getMetrics()->getStats();
    }
}
