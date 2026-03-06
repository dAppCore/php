<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Helpers;

use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Rate limiter for login attempts.
 *
 * Prevents brute force attacks by limiting failed login attempts
 * per email/IP combination.
 */
class LoginRateLimiter
{
    /**
     * Maximum login attempts before throttling.
     */
    private const MAX_ATTEMPTS = 5;

    /**
     * Lockout duration in seconds.
     */
    private const DECAY_SECONDS = 60;

    public function __construct(
        protected readonly RateLimiter $limiter
    ) {}

    /**
     * Get number of login attempts for this request.
     */
    public function attempts(Request $request): int
    {
        return $this->limiter->attempts($this->throttleKey($request));
    }

    /**
     * Check if too many login attempts have been made.
     */
    public function tooManyAttempts(Request $request): bool
    {
        return $this->limiter->tooManyAttempts(
            $this->throttleKey($request),
            self::MAX_ATTEMPTS
        );
    }

    /**
     * Increment login attempt counter.
     */
    public function increment(Request $request): void
    {
        $this->limiter->hit(
            $this->throttleKey($request),
            self::DECAY_SECONDS
        );
    }

    /**
     * Get seconds until rate limit resets.
     */
    public function availableIn(Request $request): int
    {
        return $this->limiter->availableIn($this->throttleKey($request));
    }

    /**
     * Clear rate limit for this request.
     */
    public function clear(Request $request): void
    {
        $this->limiter->clear($this->throttleKey($request));
    }

    /**
     * Generate throttle key from email and IP.
     */
    protected function throttleKey(Request $request): string
    {
        $email = Str::transliterate(Str::lower($request->input('email', '')));
        $ip = $request->ip();

        return "{$email}|{$ip}";
    }
}
