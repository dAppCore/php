<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Storage;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Log;

/**
 * Collects and reports storage metrics for monitoring.
 *
 * Tracks cache hits, misses, latency, and fallback activations to provide
 * insights into storage layer health and performance.
 *
 * ## Metrics Collected
 *
 * - **cache.hit**: Successful cache retrievals
 * - **cache.miss**: Failed cache lookups
 * - **cache.write**: Cache write operations
 * - **cache.delete**: Cache deletion operations
 * - **cache.latency**: Operation timing in milliseconds
 * - **fallback.activated**: Fallback to database triggered
 * - **circuit.opened**: Circuit breaker opened
 * - **circuit.closed**: Circuit breaker recovered
 *
 * ## Usage
 *
 * ```php
 * $metrics = app(StorageMetrics::class);
 *
 * // Record a cache hit with timing
 * $start = microtime(true);
 * $value = $cache->get($key);
 * $metrics->recordHit('redis', microtime(true) - $start);
 *
 * // Get collected metrics
 * $stats = $metrics->getStats();
 *
 * // Flush metrics (e.g., to external service)
 * $metrics->flush(fn($stats) => $statsd->gauge('cache', $stats));
 * ```
 */
class StorageMetrics
{
    /**
     * In-memory metrics buffer.
     *
     * @var array<string, array<string, int|float>>
     */
    protected array $metrics = [];

    /**
     * Timing samples for latency calculations.
     *
     * @var array<string, array<float>>
     */
    protected array $latencies = [];

    /**
     * Maximum latency samples to keep per driver.
     */
    protected int $maxLatencySamples;

    /**
     * Whether metrics collection is enabled.
     */
    protected bool $enabled;

    public function __construct(
        protected ?Dispatcher $events = null
    ) {
        $this->enabled = (bool) config('core.storage.metrics.enabled', true);
        $this->maxLatencySamples = (int) config('core.storage.metrics.max_samples', 1000);

        $this->initializeMetrics();
    }

    /**
     * Record a cache hit.
     */
    public function recordHit(string $driver, float $durationSeconds = 0.0): void
    {
        if (! $this->enabled) {
            return;
        }

        $this->doIncrement($driver, 'hits');
        $this->recordLatency($driver, $durationSeconds * 1000);
    }

    /**
     * Record a cache miss.
     */
    public function recordMiss(string $driver, float $durationSeconds = 0.0): void
    {
        if (! $this->enabled) {
            return;
        }

        $this->doIncrement($driver, 'misses');
        $this->recordLatency($driver, $durationSeconds * 1000);
    }

    /**
     * Record a cache write operation.
     */
    public function recordWrite(string $driver, float $durationSeconds = 0.0): void
    {
        if (! $this->enabled) {
            return;
        }

        $this->doIncrement($driver, 'writes');
        $this->recordLatency($driver, $durationSeconds * 1000);
    }

    /**
     * Record a cache delete operation.
     */
    public function recordDelete(string $driver, float $durationSeconds = 0.0): void
    {
        if (! $this->enabled) {
            return;
        }

        $this->doIncrement($driver, 'deletes');
        $this->recordLatency($driver, $durationSeconds * 1000);
    }

    /**
     * Record a fallback activation.
     */
    public function recordFallbackActivation(string $driver, string $reason = ''): void
    {
        if (! $this->enabled) {
            return;
        }

        $this->doIncrement($driver, 'fallback_activations');

        $this->log('warning', 'Storage fallback activated', [
            'driver' => $driver,
            'reason' => $reason,
        ]);
    }

    /**
     * Record a circuit breaker state change.
     */
    public function recordCircuitChange(string $driver, string $oldState, string $newState): void
    {
        if (! $this->enabled) {
            return;
        }

        if ($newState === CircuitBreaker::STATE_OPEN) {
            $this->doIncrement($driver, 'circuit_opens');
        } elseif ($newState === CircuitBreaker::STATE_CLOSED && $oldState !== CircuitBreaker::STATE_CLOSED) {
            $this->doIncrement($driver, 'circuit_closes');
        }

        $this->log('info', 'Circuit breaker state change', [
            'driver' => $driver,
            'old_state' => $oldState,
            'new_state' => $newState,
        ]);
    }

    /**
     * Increment a custom metric counter.
     *
     * Allows external code to record custom metrics beyond the standard
     * hit/miss/write/delete metrics.
     */
    public function increment(string $driver, string $metric, int $amount = 1): void
    {
        $this->doIncrement($driver, $metric, $amount);
    }

    /**
     * Record an error.
     */
    public function recordError(string $driver, string $operation, \Throwable $error): void
    {
        if (! $this->enabled) {
            return;
        }

        $this->doIncrement($driver, 'errors');

        $this->log('error', 'Storage operation error', [
            'driver' => $driver,
            'operation' => $operation,
            'error' => $error->getMessage(),
            'exception' => get_class($error),
        ]);
    }

    /**
     * Get metrics for all drivers.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getStats(): array
    {
        $stats = [];

        foreach ($this->metrics as $driver => $driverMetrics) {
            $latency = $this->calculateLatencyStats($driver);

            $stats[$driver] = array_merge($driverMetrics, [
                'hit_rate' => $this->calculateHitRate($driver),
                'latency_avg_ms' => $latency['avg'],
                'latency_p95_ms' => $latency['p95'],
                'latency_p99_ms' => $latency['p99'],
            ]);
        }

        return $stats;
    }

    /**
     * Get metrics for a specific driver.
     *
     * @return array<string, mixed>
     */
    public function getDriverStats(string $driver): array
    {
        $allStats = $this->getStats();

        return $allStats[$driver] ?? [];
    }

    /**
     * Flush metrics to an external handler.
     *
     * @param  callable(array<string, array<string, mixed>>): void  $handler
     */
    public function flush(callable $handler): void
    {
        $stats = $this->getStats();
        $handler($stats);
        $this->reset();
    }

    /**
     * Reset all metrics.
     */
    public function reset(): void
    {
        $this->initializeMetrics();
        $this->latencies = [];
    }

    /**
     * Enable or disable metrics collection.
     */
    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    /**
     * Check if metrics collection is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Get cache hit rate for a specific driver.
     *
     * Returns the percentage of cache hits vs total lookups (hits + misses).
     * This is a key metric for understanding cache effectiveness.
     *
     * @param  string  $driver  The cache driver name (redis, database, file, array)
     * @return float Hit rate as a percentage (0.0 to 100.0)
     */
    public function getHitRate(string $driver): float
    {
        return $this->calculateHitRate($driver);
    }

    /**
     * Get cache hit rates for all drivers.
     *
     * Returns an array of driver => hit rate percentage.
     * Useful for monitoring dashboards and alerting.
     *
     * @return array<string, float> Map of driver name to hit rate percentage
     */
    public function getAllHitRates(): array
    {
        $rates = [];

        foreach (array_keys($this->metrics) as $driver) {
            $rates[$driver] = $this->calculateHitRate($driver);
        }

        return $rates;
    }

    /**
     * Get detailed hit/miss statistics for a driver.
     *
     * Returns an array with hits, misses, total lookups, and hit rate.
     * Provides more context than just the hit rate percentage.
     *
     * @param  string  $driver  The cache driver name
     * @return array{hits: int, misses: int, total: int, hit_rate: float}
     */
    public function getHitRateDetails(string $driver): array
    {
        $hits = $this->metrics[$driver]['hits'] ?? 0;
        $misses = $this->metrics[$driver]['misses'] ?? 0;
        $total = $hits + $misses;

        return [
            'hits' => $hits,
            'misses' => $misses,
            'total' => $total,
            'hit_rate' => $this->calculateHitRate($driver),
        ];
    }

    /**
     * Check if the hit rate is below a threshold (indicating potential issues).
     *
     * Useful for alerting when cache effectiveness drops.
     *
     * @param  string  $driver  The cache driver name
     * @param  float  $threshold  Minimum acceptable hit rate percentage (default: 50.0)
     * @param  int  $minSamples  Minimum samples required before alerting (default: 100)
     * @return bool True if hit rate is below threshold and we have enough samples
     */
    public function isHitRateLow(string $driver, float $threshold = 50.0, int $minSamples = 100): bool
    {
        $hits = $this->metrics[$driver]['hits'] ?? 0;
        $misses = $this->metrics[$driver]['misses'] ?? 0;
        $total = $hits + $misses;

        // Don't alert if we don't have enough samples
        if ($total < $minSamples) {
            return false;
        }

        return $this->calculateHitRate($driver) < $threshold;
    }

    /**
     * Get a summary of cache health across all drivers.
     *
     * Returns a structured summary suitable for monitoring endpoints.
     *
     * @param  float  $warnThreshold  Hit rate below this triggers warning (default: 70.0)
     * @param  float  $criticalThreshold  Hit rate below this triggers critical (default: 50.0)
     * @return array{
     *     overall_status: string,
     *     drivers: array<string, array{hit_rate: float, status: string, hits: int, misses: int}>
     * }
     */
    public function getCacheHealthSummary(float $warnThreshold = 70.0, float $criticalThreshold = 50.0): array
    {
        $drivers = [];
        $worstStatus = 'healthy';

        foreach (array_keys($this->metrics) as $driver) {
            $details = $this->getHitRateDetails($driver);

            // Determine status
            $status = 'healthy';
            if ($details['total'] > 0) {
                if ($details['hit_rate'] < $criticalThreshold) {
                    $status = 'critical';
                } elseif ($details['hit_rate'] < $warnThreshold) {
                    $status = 'warning';
                }
            } else {
                $status = 'no_data';
            }

            // Track worst status
            if ($status === 'critical') {
                $worstStatus = 'critical';
            } elseif ($status === 'warning' && $worstStatus !== 'critical') {
                $worstStatus = 'warning';
            }

            $drivers[$driver] = [
                'hit_rate' => $details['hit_rate'],
                'status' => $status,
                'hits' => $details['hits'],
                'misses' => $details['misses'],
            ];
        }

        return [
            'overall_status' => $worstStatus,
            'drivers' => $drivers,
        ];
    }

    /**
     * Initialize metric counters.
     */
    protected function initializeMetrics(): void
    {
        $this->metrics = [
            'redis' => $this->getDefaultMetrics(),
            'database' => $this->getDefaultMetrics(),
            'file' => $this->getDefaultMetrics(),
            'array' => $this->getDefaultMetrics(),
        ];
    }

    /**
     * Get default metric structure.
     *
     * @return array<string, int>
     */
    protected function getDefaultMetrics(): array
    {
        return [
            'hits' => 0,
            'misses' => 0,
            'writes' => 0,
            'deletes' => 0,
            'errors' => 0,
            'fallback_activations' => 0,
            'circuit_opens' => 0,
            'circuit_closes' => 0,
        ];
    }

    /**
     * Internal metric counter increment.
     */
    protected function doIncrement(string $driver, string $metric, int $amount = 1): void
    {
        if (! isset($this->metrics[$driver])) {
            $this->metrics[$driver] = $this->getDefaultMetrics();
        }

        if (! isset($this->metrics[$driver][$metric])) {
            $this->metrics[$driver][$metric] = 0;
        }

        $this->metrics[$driver][$metric] += $amount;
    }

    /**
     * Record a latency sample.
     */
    protected function recordLatency(string $driver, float $durationMs): void
    {
        if (! isset($this->latencies[$driver])) {
            $this->latencies[$driver] = [];
        }

        $this->latencies[$driver][] = $durationMs;

        // Trim samples if we exceed max
        if (count($this->latencies[$driver]) > $this->maxLatencySamples) {
            $this->latencies[$driver] = array_slice(
                $this->latencies[$driver],
                -$this->maxLatencySamples
            );
        }
    }

    /**
     * Calculate hit rate for a driver.
     */
    protected function calculateHitRate(string $driver): float
    {
        $hits = $this->metrics[$driver]['hits'] ?? 0;
        $misses = $this->metrics[$driver]['misses'] ?? 0;
        $total = $hits + $misses;

        if ($total === 0) {
            return 0.0;
        }

        return round($hits / $total * 100, 2);
    }

    /**
     * Calculate latency statistics for a driver.
     *
     * @return array<string, float>
     */
    protected function calculateLatencyStats(string $driver): array
    {
        $samples = $this->latencies[$driver] ?? [];

        if (empty($samples)) {
            return ['avg' => 0.0, 'p95' => 0.0, 'p99' => 0.0];
        }

        sort($samples);
        $count = count($samples);

        $avg = round(array_sum($samples) / $count, 2);
        $p95 = round($samples[(int) floor($count * 0.95)] ?? 0, 2);
        $p99 = round($samples[(int) floor($count * 0.99)] ?? 0, 2);

        return ['avg' => $avg, 'p95' => $p95, 'p99' => $p99];
    }

    /**
     * Log a message if logging is enabled.
     *
     * @param  array<string, mixed>  $context
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        if (! config('core.storage.metrics.log_enabled', true)) {
            return;
        }

        Log::log($level, "[StorageMetrics] {$message}", $context);
    }
}
