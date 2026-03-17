<?php

declare(strict_types=1);

namespace Core\Tests\Feature;

use Core\Actions\Scheduled;
use Core\Actions\ScheduledActionScanner;
use Core\Tests\Fixtures\Mod\Scheduled\Actions\DailyAction;
use Core\Tests\Fixtures\Mod\Scheduled\Actions\EveryMinuteAction;
use PHPUnit\Framework\TestCase;

class ScheduledActionScannerTest extends TestCase
{
    private ScheduledActionScanner $scanner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scanner = new ScheduledActionScanner;
    }

    public function test_scan_discovers_scheduled_actions(): void
    {
        $results = $this->scanner->scan([
            dirname(__DIR__).'/Fixtures/Mod/Scheduled',
        ]);

        $this->assertArrayHasKey(EveryMinuteAction::class, $results);
        $this->assertArrayHasKey(DailyAction::class, $results);
    }

    public function test_scan_ignores_non_scheduled_actions(): void
    {
        $results = $this->scanner->scan([
            dirname(__DIR__).'/Fixtures/Mod/Scheduled',
        ]);

        $classes = array_keys($results);
        foreach ($classes as $class) {
            $this->assertStringNotContainsString('NotScheduled', $class);
        }
    }

    public function test_scan_returns_attribute_instances(): void
    {
        $results = $this->scanner->scan([
            dirname(__DIR__).'/Fixtures/Mod/Scheduled',
        ]);

        $attr = $results[EveryMinuteAction::class];
        $this->assertInstanceOf(Scheduled::class, $attr);
        $this->assertSame('everyMinute', $attr->frequency);
    }

    public function test_scan_preserves_attribute_parameters(): void
    {
        $results = $this->scanner->scan([
            dirname(__DIR__).'/Fixtures/Mod/Scheduled',
        ]);

        $attr = $results[DailyAction::class];
        $this->assertSame('dailyAt:09:00', $attr->frequency);
        $this->assertSame('Europe/London', $attr->timezone);
        $this->assertFalse($attr->withoutOverlapping);
    }

    public function test_scan_handles_empty_directory(): void
    {
        $results = $this->scanner->scan(['/nonexistent/path']);
        $this->assertEmpty($results);
    }

    public function test_scan_skips_test_directories(): void
    {
        $results = $this->scanner->scan([
            dirname(__DIR__).'/Fixtures/Mod/Scheduled',
        ]);

        // FakeScheduledTest is inside a Tests/ directory and should be skipped
        $classes = array_keys($results);
        foreach ($classes as $class) {
            $this->assertStringNotContainsString('FakeScheduledTest', $class);
        }

        // But real actions are still discovered
        $this->assertArrayHasKey(EveryMinuteAction::class, $results);
        $this->assertArrayHasKey(DailyAction::class, $results);
    }
}
