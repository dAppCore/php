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
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ServiceStatusTest extends TestCase
{
    #[Test]
    public function it_has_expected_values(): void
    {
        $this->assertSame('healthy', ServiceStatus::HEALTHY->value);
        $this->assertSame('degraded', ServiceStatus::DEGRADED->value);
        $this->assertSame('unhealthy', ServiceStatus::UNHEALTHY->value);
        $this->assertSame('unknown', ServiceStatus::UNKNOWN->value);
    }

    #[Test]
    public function it_checks_operational_status(): void
    {
        $this->assertTrue(ServiceStatus::HEALTHY->isOperational());
        $this->assertTrue(ServiceStatus::DEGRADED->isOperational());
        $this->assertFalse(ServiceStatus::UNHEALTHY->isOperational());
        $this->assertFalse(ServiceStatus::UNKNOWN->isOperational());
    }

    #[Test]
    public function it_provides_human_readable_labels(): void
    {
        $this->assertSame('Healthy', ServiceStatus::HEALTHY->label());
        $this->assertSame('Degraded', ServiceStatus::DEGRADED->label());
        $this->assertSame('Unhealthy', ServiceStatus::UNHEALTHY->label());
        $this->assertSame('Unknown', ServiceStatus::UNKNOWN->label());
    }

    #[Test]
    public function it_has_severity_ordering(): void
    {
        $this->assertLessThan(
            ServiceStatus::DEGRADED->severity(),
            ServiceStatus::HEALTHY->severity()
        );

        $this->assertLessThan(
            ServiceStatus::UNHEALTHY->severity(),
            ServiceStatus::DEGRADED->severity()
        );

        $this->assertLessThan(
            ServiceStatus::UNKNOWN->severity(),
            ServiceStatus::UNHEALTHY->severity()
        );
    }

    #[Test]
    public function it_creates_from_boolean(): void
    {
        $this->assertSame(ServiceStatus::HEALTHY, ServiceStatus::fromBoolean(true));
        $this->assertSame(ServiceStatus::UNHEALTHY, ServiceStatus::fromBoolean(false));
    }

    #[Test]
    public function it_finds_worst_status(): void
    {
        $statuses = [
            ServiceStatus::HEALTHY,
            ServiceStatus::DEGRADED,
            ServiceStatus::HEALTHY,
        ];

        $this->assertSame(ServiceStatus::DEGRADED, ServiceStatus::worst($statuses));
    }

    #[Test]
    public function it_returns_unknown_for_empty_array(): void
    {
        $this->assertSame(ServiceStatus::UNKNOWN, ServiceStatus::worst([]));
    }

    #[Test]
    public function it_finds_most_severe_status(): void
    {
        $statuses = [
            ServiceStatus::HEALTHY,
            ServiceStatus::UNHEALTHY,
            ServiceStatus::DEGRADED,
        ];

        $this->assertSame(ServiceStatus::UNHEALTHY, ServiceStatus::worst($statuses));
    }
}
