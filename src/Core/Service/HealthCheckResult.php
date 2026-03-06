<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Service;

use Core\Service\Enums\ServiceStatus;

/**
 * Result of a service health check.
 *
 * Encapsulates the status, message, and any diagnostic data
 * returned from a health check operation.
 */
final readonly class HealthCheckResult
{
    /**
     * @param  array<string, mixed>  $data  Additional diagnostic data
     */
    public function __construct(
        public ServiceStatus $status,
        public string $message = '',
        public array $data = [],
        public ?float $responseTimeMs = null,
    ) {}

    /**
     * Create a healthy result.
     *
     * @param  array<string, mixed>  $data
     */
    public static function healthy(string $message = 'Service is healthy', array $data = [], ?float $responseTimeMs = null): self
    {
        return new self(ServiceStatus::HEALTHY, $message, $data, $responseTimeMs);
    }

    /**
     * Create a degraded result.
     *
     * @param  array<string, mixed>  $data
     */
    public static function degraded(string $message, array $data = [], ?float $responseTimeMs = null): self
    {
        return new self(ServiceStatus::DEGRADED, $message, $data, $responseTimeMs);
    }

    /**
     * Create an unhealthy result.
     *
     * @param  array<string, mixed>  $data
     */
    public static function unhealthy(string $message, array $data = [], ?float $responseTimeMs = null): self
    {
        return new self(ServiceStatus::UNHEALTHY, $message, $data, $responseTimeMs);
    }

    /**
     * Create an unknown status result.
     *
     * @param  array<string, mixed>  $data
     */
    public static function unknown(string $message = 'Health check not available', array $data = []): self
    {
        return new self(ServiceStatus::UNKNOWN, $message, $data);
    }

    /**
     * Create a result from an exception.
     */
    public static function fromException(\Throwable $e): self
    {
        return self::unhealthy(
            message: $e->getMessage(),
            data: [
                'exception' => get_class($e),
                'code' => $e->getCode(),
            ]
        );
    }

    /**
     * Check if the result indicates operational status.
     */
    public function isOperational(): bool
    {
        return $this->status->isOperational();
    }

    /**
     * Convert to array for JSON serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'status' => $this->status->value,
            'message' => $this->message,
            'data' => $this->data ?: null,
            'response_time_ms' => $this->responseTimeMs,
        ], fn ($v) => $v !== null);
    }
}
