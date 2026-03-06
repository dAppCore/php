<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Events;

use Illuminate\Support\Facades\Log;

/**
 * Tracks lifecycle event execution for debugging and monitoring.
 *
 * EventAuditLog records when lifecycle events fire and which handlers respond,
 * including timing information and success/failure status. This is invaluable for:
 *
 * - **Debugging** - Understanding why modules aren't loading
 * - **Performance** - Identifying slow event handlers
 * - **Monitoring** - Tracking application bootstrap flow
 * - **Diagnostics** - Finding failed handlers in production
 *
 * ## Enabling Audit Logging
 *
 * Logging is disabled by default for performance. Enable it when needed:
 *
 * ```php
 * EventAuditLog::enable();      // Enable in-memory logging
 * EventAuditLog::enableLog();   // Also write to Laravel log channel
 * ```
 *
 * ## Retrieving Entries
 *
 * ```php
 * $entries = EventAuditLog::entries();           // All entries
 * $failures = EventAuditLog::failures();         // Only failed handlers
 * $webEntries = EventAuditLog::entriesFor(WebRoutesRegistering::class);
 * $summary = EventAuditLog::summary();           // Statistics
 * ```
 *
 * ## Entry Structure
 *
 * Each entry contains:
 * - `event` - Event class name
 * - `handler` - Handler module class name
 * - `duration_ms` - Execution time in milliseconds
 * - `failed` - Whether the handler threw an exception
 * - `error` - Error message (if failed)
 * - `timestamp` - Unix timestamp with microseconds
 *
 * ## Integration with LazyModuleListener
 *
 * The `LazyModuleListener` automatically records to EventAuditLog when
 * enabled, so you don't need to manually instrument event handlers.
 *
 *
 * @see LazyModuleListener For automatic audit logging integration
 */
class EventAuditLog
{
    private static bool $enabled = false;

    private static bool $logEnabled = false;

    /** @var array<int, array{event: string, handler: string, duration_ms: float, failed: bool, error?: string, timestamp: float}> */
    private static array $entries = [];

    /** @var array<string, float> */
    private static array $pendingEvents = [];

    /**
     * Enable audit logging.
     */
    public static function enable(): void
    {
        self::$enabled = true;
    }

    /**
     * Disable audit logging.
     */
    public static function disable(): void
    {
        self::$enabled = false;
    }

    /**
     * Check if audit logging is enabled.
     */
    public static function isEnabled(): bool
    {
        return self::$enabled;
    }

    /**
     * Enable writing audit entries to Laravel log.
     */
    public static function enableLog(): void
    {
        self::$logEnabled = true;
    }

    /**
     * Disable writing audit entries to Laravel log.
     */
    public static function disableLog(): void
    {
        self::$logEnabled = false;
    }

    /**
     * Record the start of event handling.
     */
    public static function recordStart(string $eventClass, string $handlerClass): void
    {
        if (! self::$enabled) {
            return;
        }

        $key = "{$eventClass}:{$handlerClass}";
        self::$pendingEvents[$key] = microtime(true);
    }

    /**
     * Record successful completion of event handling.
     */
    public static function recordSuccess(string $eventClass, string $handlerClass): void
    {
        if (! self::$enabled) {
            return;
        }

        $key = "{$eventClass}:{$handlerClass}";
        $startTime = self::$pendingEvents[$key] ?? microtime(true);
        $duration = (microtime(true) - $startTime) * 1000;

        unset(self::$pendingEvents[$key]);

        $entry = [
            'event' => $eventClass,
            'handler' => $handlerClass,
            'duration_ms' => round($duration, 2),
            'failed' => false,
            'timestamp' => microtime(true),
        ];

        self::$entries[] = $entry;

        if (self::$logEnabled) {
            Log::debug('Lifecycle event handled', $entry);
        }
    }

    /**
     * Record a failed event handler.
     */
    public static function recordFailure(string $eventClass, string $handlerClass, \Throwable $error): void
    {
        if (! self::$enabled) {
            return;
        }

        $key = "{$eventClass}:{$handlerClass}";
        $startTime = self::$pendingEvents[$key] ?? microtime(true);
        $duration = (microtime(true) - $startTime) * 1000;

        unset(self::$pendingEvents[$key]);

        $entry = [
            'event' => $eventClass,
            'handler' => $handlerClass,
            'duration_ms' => round($duration, 2),
            'failed' => true,
            'error' => $error->getMessage(),
            'timestamp' => microtime(true),
        ];

        self::$entries[] = $entry;

        if (self::$logEnabled) {
            Log::warning('Lifecycle event handler failed', $entry);
        }
    }

    /**
     * Get all recorded entries.
     *
     * @return array<int, array{event: string, handler: string, duration_ms: float, failed: bool, error?: string, timestamp: float}>
     */
    public static function entries(): array
    {
        return self::$entries;
    }

    /**
     * Get entries for a specific event class.
     *
     * @return array<int, array{event: string, handler: string, duration_ms: float, failed: bool, error?: string, timestamp: float}>
     */
    public static function entriesFor(string $eventClass): array
    {
        return array_values(
            array_filter(self::$entries, fn ($entry) => $entry['event'] === $eventClass)
        );
    }

    /**
     * Get only failed entries.
     *
     * @return array<int, array{event: string, handler: string, duration_ms: float, failed: bool, error: string, timestamp: float}>
     */
    public static function failures(): array
    {
        return array_values(
            array_filter(self::$entries, fn ($entry) => $entry['failed'])
        );
    }

    /**
     * Get summary statistics.
     *
     * @return array{total: int, failed: int, total_duration_ms: float, events: array<string, int>}
     */
    public static function summary(): array
    {
        $eventCounts = [];
        $totalDuration = 0.0;
        $failedCount = 0;

        foreach (self::$entries as $entry) {
            $eventCounts[$entry['event']] = ($eventCounts[$entry['event']] ?? 0) + 1;
            $totalDuration += $entry['duration_ms'];

            if ($entry['failed']) {
                $failedCount++;
            }
        }

        return [
            'total' => count(self::$entries),
            'failed' => $failedCount,
            'total_duration_ms' => round($totalDuration, 2),
            'events' => $eventCounts,
        ];
    }

    /**
     * Clear all recorded entries.
     */
    public static function clear(): void
    {
        self::$entries = [];
        self::$pendingEvents = [];
    }

    /**
     * Reset to initial state (disable and clear).
     */
    public static function reset(): void
    {
        self::$enabled = false;
        self::$logEnabled = false;
        self::clear();
    }
}
