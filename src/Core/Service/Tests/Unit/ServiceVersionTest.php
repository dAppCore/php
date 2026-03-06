<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Service\Tests\Unit;

use Core\Service\ServiceVersion;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ServiceVersionTest extends TestCase
{
    #[Test]
    public function it_creates_version_with_all_components(): void
    {
        $version = new ServiceVersion(2, 3, 4);

        $this->assertSame(2, $version->major);
        $this->assertSame(3, $version->minor);
        $this->assertSame(4, $version->patch);
        $this->assertFalse($version->deprecated);
        $this->assertNull($version->deprecationMessage);
        $this->assertNull($version->sunsetDate);
    }

    #[Test]
    public function it_creates_initial_version(): void
    {
        $version = ServiceVersion::initial();

        $this->assertSame('1.0.0', $version->toString());
    }

    #[Test]
    public function it_creates_version_from_string(): void
    {
        $version = ServiceVersion::fromString('2.3.4');

        $this->assertSame(2, $version->major);
        $this->assertSame(3, $version->minor);
        $this->assertSame(4, $version->patch);
    }

    #[Test]
    public function it_handles_string_with_v_prefix(): void
    {
        $version = ServiceVersion::fromString('v1.2.3');

        $this->assertSame('1.2.3', $version->toString());
    }

    #[Test]
    public function it_handles_partial_version_string(): void
    {
        $version = ServiceVersion::fromString('2');

        $this->assertSame(2, $version->major);
        $this->assertSame(0, $version->minor);
        $this->assertSame(0, $version->patch);
    }

    #[Test]
    public function it_marks_version_as_deprecated(): void
    {
        $version = new ServiceVersion(1, 0, 0);
        $sunset = new \DateTimeImmutable('2025-06-01');

        $deprecated = $version->deprecate('Use v2 instead', $sunset);

        $this->assertTrue($deprecated->deprecated);
        $this->assertSame('Use v2 instead', $deprecated->deprecationMessage);
        $this->assertEquals($sunset, $deprecated->sunsetDate);
        // Original version should be unchanged
        $this->assertFalse($version->deprecated);
    }

    #[Test]
    public function it_detects_past_sunset_date(): void
    {
        $version = (new ServiceVersion(1, 0, 0))
            ->deprecate('Old version', new \DateTimeImmutable('2020-01-01'));

        $this->assertTrue($version->isPastSunset());
    }

    #[Test]
    public function it_detects_future_sunset_date(): void
    {
        $version = (new ServiceVersion(1, 0, 0))
            ->deprecate('Will be removed', new \DateTimeImmutable('2099-01-01'));

        $this->assertFalse($version->isPastSunset());
    }

    #[Test]
    public function it_compares_versions_correctly(): void
    {
        $v1 = new ServiceVersion(1, 0, 0);
        $v2 = new ServiceVersion(2, 0, 0);
        $v1_1 = new ServiceVersion(1, 1, 0);
        $v1_0_1 = new ServiceVersion(1, 0, 1);

        $this->assertSame(-1, $v1->compare($v2));
        $this->assertSame(1, $v2->compare($v1));
        $this->assertSame(0, $v1->compare(new ServiceVersion(1, 0, 0)));
        $this->assertSame(-1, $v1->compare($v1_1));
        $this->assertSame(-1, $v1->compare($v1_0_1));
    }

    #[Test]
    public function it_checks_compatibility(): void
    {
        $v2_3 = new ServiceVersion(2, 3, 0);
        $v2_1 = new ServiceVersion(2, 1, 0);
        $v2_5 = new ServiceVersion(2, 5, 0);
        $v3_0 = new ServiceVersion(3, 0, 0);

        $this->assertTrue($v2_3->isCompatibleWith($v2_1));
        $this->assertFalse($v2_3->isCompatibleWith($v2_5));
        $this->assertFalse($v2_3->isCompatibleWith($v3_0));
    }

    #[Test]
    public function it_converts_to_string(): void
    {
        $version = new ServiceVersion(1, 2, 3);

        $this->assertSame('1.2.3', $version->toString());
        $this->assertSame('1.2.3', (string) $version);
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $version = (new ServiceVersion(1, 2, 3))
            ->deprecate('Use v2', new \DateTimeImmutable('2025-06-01'));

        $array = $version->toArray();

        $this->assertSame('1.2.3', $array['version']);
        $this->assertTrue($array['deprecated']);
        $this->assertSame('Use v2', $array['deprecation_message']);
        $this->assertSame('2025-06-01', $array['sunset_date']);
    }

    #[Test]
    public function it_omits_null_values_in_array(): void
    {
        $version = new ServiceVersion(1, 0, 0);

        $array = $version->toArray();

        $this->assertArrayHasKey('version', $array);
        $this->assertArrayNotHasKey('deprecated', $array);
        $this->assertArrayNotHasKey('deprecation_message', $array);
        $this->assertArrayNotHasKey('sunset_date', $array);
    }
}
