<?php

declare(strict_types=1);

namespace Core\Tests\Feature;

use Core\Actions\Scheduled;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class ScheduledAttributeTest extends TestCase
{
    public function test_attribute_can_be_instantiated_with_frequency(): void
    {
        $attr = new Scheduled(frequency: 'dailyAt:09:00');

        $this->assertSame('dailyAt:09:00', $attr->frequency);
        $this->assertNull($attr->timezone);
        $this->assertTrue($attr->withoutOverlapping);
        $this->assertTrue($attr->runInBackground);
    }

    public function test_attribute_accepts_all_parameters(): void
    {
        $attr = new Scheduled(
            frequency: 'weeklyOn:1,09:00',
            timezone: 'Europe/London',
            withoutOverlapping: false,
            runInBackground: false,
        );

        $this->assertSame('weeklyOn:1,09:00', $attr->frequency);
        $this->assertSame('Europe/London', $attr->timezone);
        $this->assertFalse($attr->withoutOverlapping);
        $this->assertFalse($attr->runInBackground);
    }

    public function test_attribute_targets_class_only(): void
    {
        $ref = new ReflectionClass(Scheduled::class);
        $attrs = $ref->getAttributes(\Attribute::class);

        $this->assertNotEmpty($attrs);
        $instance = $attrs[0]->newInstance();
        $this->assertSame(\Attribute::TARGET_CLASS, $instance->flags);
    }

    public function test_attribute_can_be_read_from_class(): void
    {
        $ref = new ReflectionClass(ScheduledAttributeTest_Stub::class);
        $attrs = $ref->getAttributes(Scheduled::class);

        $this->assertCount(1, $attrs);
        $instance = $attrs[0]->newInstance();
        $this->assertSame('everyMinute', $instance->frequency);
    }
}

#[Scheduled(frequency: 'everyMinute')]
class ScheduledAttributeTest_Stub {}
