<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Service\Contracts;

use Core\Service\HealthCheckResult;

/**
 * Contract for services that provide health checks.
 *
 * Services implementing this interface can report their operational status
 * for monitoring, load balancing, and alerting purposes. Health endpoints
 * can aggregate results from all registered HealthCheckable services.
 *
 * ## Health Check Guidelines
 *
 * Health checks should be:
 *
 * - **Fast** - Complete within 5 seconds (preferably < 1 second)
 * - **Non-destructive** - Perform read-only operations only
 * - **Representative** - Actually test the critical dependencies
 * - **Safe** - Handle all exceptions and return HealthCheckResult
 *
 * ## Result States
 *
 * Use `HealthCheckResult` factory methods:
 *
 * - `healthy()` - Service is fully operational
 * - `degraded()` - Service works but with reduced performance/capability
 * - `unhealthy()` - Service is not operational
 * - `fromException()` - Convert exception to unhealthy result
 *
 * ## Example Implementation
 *
 * ```php
 * public function healthCheck(): HealthCheckResult
 * {
 *     try {
 *         $start = microtime(true);
 *         $this->database->select('SELECT 1');
 *         $responseTime = (microtime(true) - $start) * 1000;
 *
 *         if ($responseTime > 1000) {
 *             return HealthCheckResult::degraded(
 *                 'Database responding slowly',
 *                 ['response_time_ms' => $responseTime]
 *             );
 *         }
 *
 *         return HealthCheckResult::healthy(
 *             'All systems operational',
 *             responseTimeMs: $responseTime
 *         );
 *     } catch (\Exception $e) {
 *         return HealthCheckResult::fromException($e);
 *     }
 * }
 * ```
 *
 *
 * @see HealthCheckResult For result factory methods
 */
interface HealthCheckable
{
    /**
     * Perform a health check and return the result.
     *
     * Implementations should catch all exceptions and return
     * an appropriate HealthCheckResult rather than throwing.
     */
    public function healthCheck(): HealthCheckResult;
}
