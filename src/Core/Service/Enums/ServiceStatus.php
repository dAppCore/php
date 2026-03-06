<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Service\Enums;

/**
 * Service operational status.
 *
 * Represents the current state of a service for health monitoring
 * and status reporting purposes.
 */
enum ServiceStatus: string
{
    case HEALTHY = 'healthy';
    case DEGRADED = 'degraded';
    case UNHEALTHY = 'unhealthy';
    case UNKNOWN = 'unknown';

    /**
     * Check if the status indicates the service is operational.
     */
    public function isOperational(): bool
    {
        return match ($this) {
            self::HEALTHY, self::DEGRADED => true,
            self::UNHEALTHY, self::UNKNOWN => false,
        };
    }

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::HEALTHY => 'Healthy',
            self::DEGRADED => 'Degraded',
            self::UNHEALTHY => 'Unhealthy',
            self::UNKNOWN => 'Unknown',
        };
    }

    /**
     * Get the severity level for logging/alerting.
     * Lower values = more severe.
     */
    public function severity(): int
    {
        return match ($this) {
            self::HEALTHY => 0,
            self::DEGRADED => 1,
            self::UNHEALTHY => 2,
            self::UNKNOWN => 3,
        };
    }

    /**
     * Create status from a boolean health check result.
     */
    public static function fromBoolean(bool $healthy): self
    {
        return $healthy ? self::HEALTHY : self::UNHEALTHY;
    }

    /**
     * Get the worst status from multiple statuses.
     *
     * @param  array<self>  $statuses
     */
    public static function worst(array $statuses): self
    {
        if (empty($statuses)) {
            return self::UNKNOWN;
        }

        $worstSeverity = -1;
        $worstStatus = self::HEALTHY;

        foreach ($statuses as $status) {
            if ($status->severity() > $worstSeverity) {
                $worstSeverity = $status->severity();
                $worstStatus = $status;
            }
        }

        return $worstStatus;
    }
}
