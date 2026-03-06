<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Storage;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;

/**
 * Circuit breaker pattern implementation for Redis resilience.
 *
 * Prevents cascading failures by temporarily "opening" the circuit when
 * Redis fails repeatedly, allowing the system to use the fallback without
 * continually attempting Redis connections.
 *
 * ## States
 *
 * - **Closed**: Normal operation, requests go to Redis
 * - **Open**: Redis failing, requests go directly to fallback
 * - **Half-Open**: Testing if Redis has recovered
 *
 * ## Configuration
 *
 * - `failure_threshold`: Number of failures before opening (default: 5)
 * - `recovery_timeout`: Seconds before attempting recovery (default: 30)
 * - `success_threshold`: Successes needed to close circuit (default: 2)
 *
 * ## Usage
 *
 * ```php
 * $breaker = new CircuitBreaker('redis');
 *
 * if ($breaker->isAvailable()) {
 *     try {
 *         $result = $redis->get($key);
 *         $breaker->recordSuccess();
 *     } catch (\Throwable $e) {
 *         $breaker->recordFailure();
 *         // Use fallback
 *     }
 * } else {
 *     // Use fallback directly, skip Redis attempt
 * }
 * ```
 */
class CircuitBreaker
{
    /**
     * Circuit states.
     */
    public const STATE_CLOSED = 'closed';

    public const STATE_OPEN = 'open';

    public const STATE_HALF_OPEN = 'half_open';

    /**
     * Cache key prefix.
     */
    protected const CACHE_PREFIX = 'circuit_breaker:';

    /**
     * @var int Number of failures before opening circuit
     */
    protected int $failureThreshold;

    /**
     * @var int Seconds to wait before attempting recovery
     */
    protected int $recoveryTimeout;

    /**
     * @var int Successes needed to close circuit from half-open
     */
    protected int $successThreshold;

    /**
     * @var CacheRepository|null Fallback cache for storing state
     */
    protected ?CacheRepository $stateCache = null;

    public function __construct(
        protected string $serviceName,
        ?int $failureThreshold = null,
        ?int $recoveryTimeout = null,
        ?int $successThreshold = null,
    ) {
        $this->failureThreshold = $failureThreshold
            ?? (int) config('core.storage.circuit_breaker.failure_threshold', 5);
        $this->recoveryTimeout = $recoveryTimeout
            ?? (int) config('core.storage.circuit_breaker.recovery_timeout', 30);
        $this->successThreshold = $successThreshold
            ?? (int) config('core.storage.circuit_breaker.success_threshold', 2);
    }

    /**
     * Check if the service is available (circuit closed or half-open).
     */
    public function isAvailable(): bool
    {
        $state = $this->getState();

        if ($state === self::STATE_CLOSED) {
            return true;
        }

        if ($state === self::STATE_OPEN) {
            // Check if recovery timeout has passed
            if ($this->shouldAttemptRecovery()) {
                $this->transitionToHalfOpen();

                return true;
            }

            return false;
        }

        // Half-open: allow one request through
        return true;
    }

    /**
     * Record a successful operation.
     */
    public function recordSuccess(): void
    {
        $state = $this->getState();

        if ($state === self::STATE_HALF_OPEN) {
            $successes = $this->incrementSuccessCount();

            if ($successes >= $this->successThreshold) {
                $this->transitionToClosed();
            }
        } elseif ($state === self::STATE_CLOSED) {
            // Reset failure count on success
            $this->resetFailureCount();
        }
    }

    /**
     * Record a failed operation.
     */
    public function recordFailure(): void
    {
        $state = $this->getState();

        if ($state === self::STATE_HALF_OPEN) {
            // Single failure in half-open reopens the circuit
            $this->transitionToOpen();
        } elseif ($state === self::STATE_CLOSED) {
            $failures = $this->incrementFailureCount();

            if ($failures >= $this->failureThreshold) {
                $this->transitionToOpen();
            }
        }
    }

    /**
     * Get the current circuit state.
     */
    public function getState(): string
    {
        return $this->getStateValue('state') ?? self::STATE_CLOSED;
    }

    /**
     * Force the circuit to a specific state.
     */
    public function forceState(string $state): void
    {
        $this->setStateValue('state', $state);
        $this->setStateValue('state_changed_at', time());

        if ($state === self::STATE_CLOSED) {
            $this->resetFailureCount();
            $this->resetSuccessCount();
        }
    }

    /**
     * Get circuit statistics.
     *
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        return [
            'service' => $this->serviceName,
            'state' => $this->getState(),
            'failure_count' => (int) $this->getStateValue('failure_count'),
            'success_count' => (int) $this->getStateValue('success_count'),
            'last_failure_at' => $this->getStateValue('last_failure_at'),
            'state_changed_at' => $this->getStateValue('state_changed_at'),
            'thresholds' => [
                'failure' => $this->failureThreshold,
                'recovery_timeout' => $this->recoveryTimeout,
                'success' => $this->successThreshold,
            ],
        ];
    }

    /**
     * Reset the circuit breaker to closed state.
     */
    public function reset(): void
    {
        $this->forceState(self::STATE_CLOSED);
    }

    /**
     * Transition to open state.
     */
    protected function transitionToOpen(): void
    {
        $this->setStateValue('state', self::STATE_OPEN);
        $this->setStateValue('state_changed_at', time());
        $this->setStateValue('last_failure_at', time());
        $this->resetSuccessCount();
    }

    /**
     * Transition to half-open state.
     */
    protected function transitionToHalfOpen(): void
    {
        $this->setStateValue('state', self::STATE_HALF_OPEN);
        $this->setStateValue('state_changed_at', time());
        $this->resetSuccessCount();
    }

    /**
     * Transition to closed state.
     */
    protected function transitionToClosed(): void
    {
        $this->setStateValue('state', self::STATE_CLOSED);
        $this->setStateValue('state_changed_at', time());
        $this->resetFailureCount();
        $this->resetSuccessCount();
    }

    /**
     * Check if recovery should be attempted.
     */
    protected function shouldAttemptRecovery(): bool
    {
        $changedAt = (int) $this->getStateValue('state_changed_at');

        return (time() - $changedAt) >= $this->recoveryTimeout;
    }

    /**
     * Increment and return failure count.
     */
    protected function incrementFailureCount(): int
    {
        $count = ((int) $this->getStateValue('failure_count')) + 1;
        $this->setStateValue('failure_count', $count);

        return $count;
    }

    /**
     * Reset failure count.
     */
    protected function resetFailureCount(): void
    {
        $this->setStateValue('failure_count', 0);
    }

    /**
     * Increment and return success count.
     */
    protected function incrementSuccessCount(): int
    {
        $count = ((int) $this->getStateValue('success_count')) + 1;
        $this->setStateValue('success_count', $count);

        return $count;
    }

    /**
     * Reset success count.
     */
    protected function resetSuccessCount(): void
    {
        $this->setStateValue('success_count', 0);
    }

    /**
     * Get a state value from storage.
     */
    protected function getStateValue(string $key): mixed
    {
        return $this->getStateCache()->get($this->getCacheKey($key));
    }

    /**
     * Set a state value in storage.
     */
    protected function setStateValue(string $key, mixed $value): void
    {
        // Store for 24 hours (enough to survive any reasonable recovery timeout)
        $this->getStateCache()->put(
            $this->getCacheKey($key),
            $value,
            86400
        );
    }

    /**
     * Get cache key for a state value.
     */
    protected function getCacheKey(string $key): string
    {
        return self::CACHE_PREFIX.$this->serviceName.':'.$key;
    }

    /**
     * Get the cache repository for storing state.
     *
     * Uses database cache to avoid circular dependency with Redis.
     */
    protected function getStateCache(): CacheRepository
    {
        if ($this->stateCache === null) {
            // Use file or database cache to avoid Redis dependency
            $driver = config('core.storage.circuit_breaker.state_driver', 'file');

            try {
                $this->stateCache = Cache::store($driver);
            } catch (\Throwable) {
                // Fallback to array cache if driver unavailable
                $this->stateCache = Cache::store('array');
            }
        }

        return $this->stateCache;
    }
}
