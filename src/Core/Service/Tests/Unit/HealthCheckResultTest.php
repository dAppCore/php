<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Service\Tests\Unit;

use Core\Service\Enums\ServiceStatus;
use Core\Service\HealthCheckResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HealthCheckResultTest extends TestCase
{
    #[Test]
    public function it_creates_healthy_result(): void
    {
        $result = HealthCheckResult::healthy('All good', ['key' => 'value'], 15.5);

        $this->assertSame(ServiceStatus::HEALTHY, $result->status);
        $this->assertSame('All good', $result->message);
        $this->assertSame(['key' => 'value'], $result->data);
        $this->assertSame(15.5, $result->responseTimeMs);
    }

    #[Test]
    public function it_creates_degraded_result(): void
    {
        $result = HealthCheckResult::degraded('Slow response');

        $this->assertSame(ServiceStatus::DEGRADED, $result->status);
        $this->assertSame('Slow response', $result->message);
    }

    #[Test]
    public function it_creates_unhealthy_result(): void
    {
        $result = HealthCheckResult::unhealthy('Connection failed');

        $this->assertSame(ServiceStatus::UNHEALTHY, $result->status);
        $this->assertSame('Connection failed', $result->message);
    }

    #[Test]
    public function it_creates_unknown_result(): void
    {
        $result = HealthCheckResult::unknown();

        $this->assertSame(ServiceStatus::UNKNOWN, $result->status);
        $this->assertSame('Health check not available', $result->message);
    }

    #[Test]
    public function it_creates_from_exception(): void
    {
        $exception = new \RuntimeException('Database error', 500);
        $result = HealthCheckResult::fromException($exception);

        $this->assertSame(ServiceStatus::UNHEALTHY, $result->status);
        $this->assertSame('Database error', $result->message);
        $this->assertSame('RuntimeException', $result->data['exception']);
        $this->assertSame(500, $result->data['code']);
    }

    #[Test]
    public function it_checks_operational_status(): void
    {
        $this->assertTrue(HealthCheckResult::healthy()->isOperational());
        $this->assertTrue(HealthCheckResult::degraded('Slow')->isOperational());
        $this->assertFalse(HealthCheckResult::unhealthy('Down')->isOperational());
        $this->assertFalse(HealthCheckResult::unknown()->isOperational());
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $result = HealthCheckResult::healthy(
            'Service operational',
            ['version' => '1.0'],
            12.34
        );

        $array = $result->toArray();

        $this->assertSame('healthy', $array['status']);
        $this->assertSame('Service operational', $array['message']);
        $this->assertSame(['version' => '1.0'], $array['data']);
        $this->assertSame(12.34, $array['response_time_ms']);
    }

    #[Test]
    public function it_omits_null_values_in_array(): void
    {
        $result = HealthCheckResult::healthy();

        $array = $result->toArray();

        $this->assertArrayHasKey('status', $array);
        $this->assertArrayHasKey('message', $array);
        $this->assertArrayNotHasKey('data', $array);
        $this->assertArrayNotHasKey('response_time_ms', $array);
    }

    #[Test]
    public function it_uses_default_healthy_message(): void
    {
        $result = HealthCheckResult::healthy();

        $this->assertSame('Service is healthy', $result->message);
    }
}
