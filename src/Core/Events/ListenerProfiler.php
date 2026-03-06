<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Events;

/**
 * Profiles event listener execution time and memory usage.
 *
 * ListenerProfiler provides detailed performance metrics for event listeners,
 * helping identify slow or memory-intensive handlers that may need optimization.
 *
 * ## Features
 *
 * - **Execution Time** - Tracks time spent in each listener (milliseconds)
 * - **Memory Usage** - Measures peak memory delta during listener execution
 * - **Call Counting** - Tracks how many times each listener is invoked
 * - **Slow Listener Detection** - Configurable threshold for identifying slow listeners
 *
 * ## Enabling Profiling
 *
 * Profiling is disabled by default for performance. Enable when needed:
 *
 * ```php
 * ListenerProfiler::enable();           // Enable profiling
 * ListenerProfiler::setSlowThreshold(50); // Flag listeners >50ms as slow
 * ```
 *
 * ## Retrieving Metrics
 *
 * ```php
 * $profiles = ListenerProfiler::getProfiles();        // All listener profiles
 * $slow = ListenerProfiler::getSlowListeners();       // Listeners exceeding threshold
 * $sorted = ListenerProfiler::getSlowest(10);         // Top 10 slowest listeners
 * $byEvent = ListenerProfiler::getProfilesForEvent(WebRoutesRegistering::class);
 * $summary = ListenerProfiler::getSummary();          // Overall statistics
 * ```
 *
 * ## Profile Structure
 *
 * Each profile contains:
 * - `event` - Event class name
 * - `handler` - Handler class name
 * - `method` - Handler method name
 * - `duration_ms` - Total execution time (milliseconds)
 * - `memory_peak_bytes` - Peak memory usage during execution
 * - `memory_delta_bytes` - Memory change during execution
 * - `call_count` - Number of invocations
 * - `avg_duration_ms` - Average time per call
 * - `is_slow` - Whether any call exceeded slow threshold
 * - `calls` - Array of individual call metrics
 *
 * ## Integration with LazyModuleListener
 *
 * Enable automatic profiling integration:
 *
 * ```php
 * ListenerProfiler::enable();
 * // Profiling is automatically integrated via LazyModuleListener
 * ```
 *
 *
 * @see EventAuditLog For simpler success/failure tracking
 * @see LazyModuleListener For automatic profiling integration
 */
class ListenerProfiler
{
    private static bool $enabled = false;

    /**
     * Threshold in milliseconds for flagging slow listeners.
     */
    private static float $slowThreshold = 100.0;

    /**
     * Collected profile data.
     *
     * @var array<string, array{
     *     event: string,
     *     handler: string,
     *     method: string,
     *     duration_ms: float,
     *     memory_peak_bytes: int,
     *     memory_delta_bytes: int,
     *     call_count: int,
     *     avg_duration_ms: float,
     *     is_slow: bool,
     *     calls: array<int, array{duration_ms: float, memory_before: int, memory_after: int, memory_peak: int}>
     * }>
     */
    private static array $profiles = [];

    /**
     * Active profiling contexts (for nested calls).
     *
     * @var array<string, array{start_time: float, memory_before: int}>
     */
    private static array $activeContexts = [];

    /**
     * Enable listener profiling.
     */
    public static function enable(): void
    {
        self::$enabled = true;
    }

    /**
     * Disable listener profiling.
     */
    public static function disable(): void
    {
        self::$enabled = false;
    }

    /**
     * Check if profiling is enabled.
     */
    public static function isEnabled(): bool
    {
        return self::$enabled;
    }

    /**
     * Set the threshold for flagging slow listeners.
     *
     * @param  float  $thresholdMs  Threshold in milliseconds
     */
    public static function setSlowThreshold(float $thresholdMs): void
    {
        self::$slowThreshold = $thresholdMs;
    }

    /**
     * Get the current slow listener threshold.
     *
     * @return float Threshold in milliseconds
     */
    public static function getSlowThreshold(): float
    {
        return self::$slowThreshold;
    }

    /**
     * Start profiling a listener execution.
     *
     * Call this before invoking the listener. Returns a context key that must
     * be passed to stop() to properly correlate the measurement.
     *
     * @param  string  $eventClass  Event being handled
     * @param  string  $handlerClass  Handler class name
     * @param  string  $method  Handler method name
     * @return string Context key for stop()
     */
    public static function start(string $eventClass, string $handlerClass, string $method = '__invoke'): string
    {
        if (! self::$enabled) {
            return '';
        }

        $contextKey = self::makeContextKey($eventClass, $handlerClass, $method);

        self::$activeContexts[$contextKey] = [
            'start_time' => hrtime(true),
            'memory_before' => memory_get_usage(true),
        ];

        return $contextKey;
    }

    /**
     * Stop profiling and record the results.
     *
     * @param  string  $contextKey  Key returned by start()
     */
    public static function stop(string $contextKey): void
    {
        if (! self::$enabled || $contextKey === '' || ! isset(self::$activeContexts[$contextKey])) {
            return;
        }

        $context = self::$activeContexts[$contextKey];
        unset(self::$activeContexts[$contextKey]);

        $endTime = hrtime(true);
        $memoryAfter = memory_get_usage(true);
        $memoryPeak = memory_get_peak_usage(true);

        $durationNs = $endTime - $context['start_time'];
        $durationMs = $durationNs / 1_000_000;

        // Parse context key to get event, handler, method
        [$eventClass, $handlerClass, $method] = self::parseContextKey($contextKey);

        $profileKey = self::makeProfileKey($eventClass, $handlerClass);

        // Initialize profile if needed
        if (! isset(self::$profiles[$profileKey])) {
            self::$profiles[$profileKey] = [
                'event' => $eventClass,
                'handler' => $handlerClass,
                'method' => $method,
                'duration_ms' => 0.0,
                'memory_peak_bytes' => 0,
                'memory_delta_bytes' => 0,
                'call_count' => 0,
                'avg_duration_ms' => 0.0,
                'is_slow' => false,
                'calls' => [],
            ];
        }

        // Record this call
        $callData = [
            'duration_ms' => round($durationMs, 3),
            'memory_before' => $context['memory_before'],
            'memory_after' => $memoryAfter,
            'memory_peak' => $memoryPeak,
        ];

        self::$profiles[$profileKey]['calls'][] = $callData;
        self::$profiles[$profileKey]['duration_ms'] += $durationMs;
        self::$profiles[$profileKey]['call_count']++;

        // Update peak memory if this call used more
        $memoryDelta = $memoryAfter - $context['memory_before'];
        if ($memoryPeak > self::$profiles[$profileKey]['memory_peak_bytes']) {
            self::$profiles[$profileKey]['memory_peak_bytes'] = $memoryPeak;
        }
        self::$profiles[$profileKey]['memory_delta_bytes'] += $memoryDelta;

        // Update average
        self::$profiles[$profileKey]['avg_duration_ms'] = round(
            self::$profiles[$profileKey]['duration_ms'] / self::$profiles[$profileKey]['call_count'],
            3
        );

        // Check if slow
        if ($durationMs >= self::$slowThreshold) {
            self::$profiles[$profileKey]['is_slow'] = true;
        }
    }

    /**
     * Profile a listener execution using a callback.
     *
     * Convenience method that handles start/stop automatically.
     *
     * ```php
     * ListenerProfiler::profile(
     *     WebRoutesRegistering::class,
     *     MyModule::class,
     *     'onWebRoutes',
     *     fn() => $handler->onWebRoutes($event)
     * );
     * ```
     *
     * @template T
     *
     * @param  string  $eventClass  Event being handled
     * @param  string  $handlerClass  Handler class name
     * @param  string  $method  Handler method name
     * @param  callable(): T  $callback  The listener callback to profile
     * @return T The callback's return value
     */
    public static function profile(string $eventClass, string $handlerClass, string $method, callable $callback): mixed
    {
        $contextKey = self::start($eventClass, $handlerClass, $method);

        try {
            return $callback();
        } finally {
            self::stop($contextKey);
        }
    }

    /**
     * Get all collected profiles.
     *
     * @return array<string, array{
     *     event: string,
     *     handler: string,
     *     method: string,
     *     duration_ms: float,
     *     memory_peak_bytes: int,
     *     memory_delta_bytes: int,
     *     call_count: int,
     *     avg_duration_ms: float,
     *     is_slow: bool,
     *     calls: array<int, array{duration_ms: float, memory_before: int, memory_after: int, memory_peak: int}>
     * }>
     */
    public static function getProfiles(): array
    {
        return self::$profiles;
    }

    /**
     * Get profiles for a specific event.
     *
     * @param  string  $eventClass  Event class name
     * @return array<string, array{
     *     event: string,
     *     handler: string,
     *     method: string,
     *     duration_ms: float,
     *     memory_peak_bytes: int,
     *     memory_delta_bytes: int,
     *     call_count: int,
     *     avg_duration_ms: float,
     *     is_slow: bool,
     *     calls: array
     * }>
     */
    public static function getProfilesForEvent(string $eventClass): array
    {
        return array_filter(
            self::$profiles,
            fn ($profile) => $profile['event'] === $eventClass
        );
    }

    /**
     * Get profiles for a specific handler.
     *
     * @param  string  $handlerClass  Handler class name
     * @return array<string, array{
     *     event: string,
     *     handler: string,
     *     method: string,
     *     duration_ms: float,
     *     memory_peak_bytes: int,
     *     memory_delta_bytes: int,
     *     call_count: int,
     *     avg_duration_ms: float,
     *     is_slow: bool,
     *     calls: array
     * }>
     */
    public static function getProfilesForHandler(string $handlerClass): array
    {
        return array_filter(
            self::$profiles,
            fn ($profile) => $profile['handler'] === $handlerClass
        );
    }

    /**
     * Get listeners that exceeded the slow threshold.
     *
     * @return array<string, array{
     *     event: string,
     *     handler: string,
     *     method: string,
     *     duration_ms: float,
     *     memory_peak_bytes: int,
     *     memory_delta_bytes: int,
     *     call_count: int,
     *     avg_duration_ms: float,
     *     is_slow: bool,
     *     calls: array
     * }>
     */
    public static function getSlowListeners(): array
    {
        return array_filter(
            self::$profiles,
            fn ($profile) => $profile['is_slow']
        );
    }

    /**
     * Get the N slowest listeners by total duration.
     *
     * @param  int  $limit  Maximum number of results
     * @return array<string, array{
     *     event: string,
     *     handler: string,
     *     method: string,
     *     duration_ms: float,
     *     memory_peak_bytes: int,
     *     memory_delta_bytes: int,
     *     call_count: int,
     *     avg_duration_ms: float,
     *     is_slow: bool,
     *     calls: array
     * }>
     */
    public static function getSlowest(int $limit = 10): array
    {
        $profiles = self::$profiles;
        uasort($profiles, fn ($a, $b) => $b['duration_ms'] <=> $a['duration_ms']);

        return array_slice($profiles, 0, $limit, true);
    }

    /**
     * Get the N highest memory-consuming listeners.
     *
     * @param  int  $limit  Maximum number of results
     * @return array<string, array{
     *     event: string,
     *     handler: string,
     *     method: string,
     *     duration_ms: float,
     *     memory_peak_bytes: int,
     *     memory_delta_bytes: int,
     *     call_count: int,
     *     avg_duration_ms: float,
     *     is_slow: bool,
     *     calls: array
     * }>
     */
    public static function getHighestMemory(int $limit = 10): array
    {
        $profiles = self::$profiles;
        uasort($profiles, fn ($a, $b) => $b['memory_delta_bytes'] <=> $a['memory_delta_bytes']);

        return array_slice($profiles, 0, $limit, true);
    }

    /**
     * Get summary statistics for all profiled listeners.
     *
     * @return array{
     *     total_listeners: int,
     *     total_calls: int,
     *     total_duration_ms: float,
     *     avg_duration_ms: float,
     *     slow_listeners: int,
     *     total_memory_delta_bytes: int,
     *     by_event: array<string, array{listeners: int, duration_ms: float, calls: int}>
     * }
     */
    public static function getSummary(): array
    {
        $totalListeners = count(self::$profiles);
        $totalCalls = 0;
        $totalDuration = 0.0;
        $slowCount = 0;
        $totalMemoryDelta = 0;
        $byEvent = [];

        foreach (self::$profiles as $profile) {
            $totalCalls += $profile['call_count'];
            $totalDuration += $profile['duration_ms'];
            $totalMemoryDelta += $profile['memory_delta_bytes'];

            if ($profile['is_slow']) {
                $slowCount++;
            }

            $event = $profile['event'];
            if (! isset($byEvent[$event])) {
                $byEvent[$event] = [
                    'listeners' => 0,
                    'duration_ms' => 0.0,
                    'calls' => 0,
                ];
            }
            $byEvent[$event]['listeners']++;
            $byEvent[$event]['duration_ms'] += $profile['duration_ms'];
            $byEvent[$event]['calls'] += $profile['call_count'];
        }

        return [
            'total_listeners' => $totalListeners,
            'total_calls' => $totalCalls,
            'total_duration_ms' => round($totalDuration, 3),
            'avg_duration_ms' => $totalCalls > 0 ? round($totalDuration / $totalCalls, 3) : 0.0,
            'slow_listeners' => $slowCount,
            'total_memory_delta_bytes' => $totalMemoryDelta,
            'by_event' => $byEvent,
        ];
    }

    /**
     * Clear all collected profiles.
     */
    public static function clear(): void
    {
        self::$profiles = [];
        self::$activeContexts = [];
    }

    /**
     * Reset to initial state (disable and clear).
     */
    public static function reset(): void
    {
        self::$enabled = false;
        self::$slowThreshold = 100.0;
        self::clear();
    }

    /**
     * Export profiles to a format suitable for analysis tools.
     *
     * @return array{
     *     timestamp: string,
     *     slow_threshold_ms: float,
     *     summary: array,
     *     profiles: array
     * }
     */
    public static function export(): array
    {
        return [
            'timestamp' => date('c'),
            'slow_threshold_ms' => self::$slowThreshold,
            'summary' => self::getSummary(),
            'profiles' => self::$profiles,
        ];
    }

    /**
     * Create a unique context key for a listener execution.
     */
    private static function makeContextKey(string $eventClass, string $handlerClass, string $method): string
    {
        $uniqueId = bin2hex(random_bytes(8));

        return "{$eventClass}|{$handlerClass}|{$method}|{$uniqueId}";
    }

    /**
     * Parse a context key back into its components.
     *
     * @return array{0: string, 1: string, 2: string} [event, handler, method]
     */
    private static function parseContextKey(string $contextKey): array
    {
        $parts = explode('|', $contextKey);

        return [$parts[0] ?? '', $parts[1] ?? '', $parts[2] ?? ''];
    }

    /**
     * Create a profile key for aggregating calls to the same listener.
     */
    private static function makeProfileKey(string $eventClass, string $handlerClass): string
    {
        return "{$eventClass}::{$handlerClass}";
    }
}
