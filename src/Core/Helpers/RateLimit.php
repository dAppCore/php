<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Helpers;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Time-window based rate limiter.
 *
 * Tracks requests over a sliding time window and enforces
 * configurable rate limits with cache-based storage.
 */
class RateLimit
{
    public function __construct(
        private readonly string $key,
        private readonly int $limit,
        private readonly int $timeframeInMinutes,
    ) {}

    /**
     * Check if rate limit has been exceeded.
     */
    public function isExceeded(): bool
    {
        $requests = $this->getRecentRequests();

        return count($requests) >= $this->limit;
    }

    /**
     * Check if we're approaching the rate limit threshold.
     */
    public function isAboutToBeExceeded(int $additionalRequests = 10): bool
    {
        $requests = $this->getRecentRequests();

        return (count($requests) + $additionalRequests) >= $this->limit;
    }

    /**
     * Get number of remaining requests in current window.
     */
    public function getRemaining(): int
    {
        $requests = $this->getRecentRequests();
        $remainingRequests = $this->limit - count($requests);

        return max($remainingRequests, 0);
    }

    /**
     * Record a new request.
     */
    public function record(): void
    {
        $requests = Cache::get($this->getCacheKey(), []);

        $requests[] = Carbon::now();

        $ttl = Carbon::now()->addMinutes($this->timeframeInMinutes + 1);

        Cache::put($this->getCacheKey(), $requests, $ttl);
    }

    /**
     * Get recent requests within the timeframe.
     *
     * @return array<int, Carbon>
     */
    private function getRecentRequests(): array
    {
        $requests = Cache::get($this->getCacheKey(), []);

        // Filter requests within the sliding window
        $minutesAgo = Carbon::now()->subMinutes($this->timeframeInMinutes);

        return array_filter($requests, function ($timestamp) use ($minutesAgo) {
            return $timestamp > $minutesAgo;
        });
    }

    /**
     * Generate cache key for this rate limiter.
     */
    private function getCacheKey(): string
    {
        return "social:{$this->key}:requests";
    }
}
