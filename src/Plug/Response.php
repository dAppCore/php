<?php

declare(strict_types=1);

namespace Core\Plug;

use Core\Plug\Enum\Status;
use Illuminate\Support\Arr;

/**
 * Standardised response for all Plug operations.
 *
 * Provides consistent interface for success/error handling across all providers.
 */
final class Response
{
    public function __construct(
        private readonly Status $status,
        private readonly array $context = [],
        private readonly bool $rateLimitApproaching = false,
        private readonly int $retryAfter = 0
    ) {}

    /**
     * Magic getter for context values.
     */
    public function __get(string $key): mixed
    {
        return Arr::get($this->context, $key);
    }

    /**
     * Get the response status.
     */
    public function status(): Status
    {
        return $this->status;
    }

    /**
     * Get the full context array.
     */
    public function context(): array
    {
        return $this->context;
    }

    /**
     * Get a specific context value.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->context, $key, $default);
    }

    /**
     * Get the primary ID from the response.
     */
    public function id(): mixed
    {
        return Arr::get($this->context, 'id');
    }

    /**
     * Check if the response indicates success.
     */
    public function isOk(): bool
    {
        return $this->status === Status::OK;
    }

    /**
     * Check if the response indicates an error.
     */
    public function hasError(): bool
    {
        return ! $this->isOk();
    }

    /**
     * Check if the response indicates unauthorized access.
     */
    public function isUnauthorized(): bool
    {
        return $this->status === Status::UNAUTHORIZED;
    }

    /**
     * Check if the response indicates rate limiting.
     */
    public function isRateLimited(): bool
    {
        return $this->status === Status::RATE_LIMITED;
    }

    /**
     * Get the retry-after value in seconds.
     */
    public function retryAfter(): int
    {
        return $this->retryAfter;
    }

    /**
     * Check if rate limit is approaching (but not exceeded).
     */
    public function rateLimitApproaching(): bool
    {
        return $this->rateLimitApproaching;
    }

    /**
     * Get the error message if present.
     */
    public function getMessage(): ?string
    {
        return Arr::get($this->context, 'message');
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'context' => $this->context,
            'rate_limit_approaching' => $this->rateLimitApproaching,
            'retry_after' => $this->retryAfter,
        ];
    }
}
