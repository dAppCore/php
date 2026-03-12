<?php

declare(strict_types=1);

namespace Core\Tests\Feature;

use Core\Actions\Action;
use Core\Actions\Scheduled;
use Core\Tests\TestCase;
use Core\Webhook\CronTrigger;
use Illuminate\Support\Facades\Http;

class CronTriggerTest extends TestCase
{
    public function test_has_scheduled_attribute(): void
    {
        $ref = new \ReflectionClass(CronTrigger::class);
        $attrs = $ref->getAttributes(Scheduled::class);

        $this->assertCount(1, $attrs);
        $this->assertSame('everyMinute', $attrs[0]->newInstance()->frequency);
    }

    public function test_uses_action_trait(): void
    {
        $this->assertTrue(
            in_array(Action::class, class_uses_recursive(CronTrigger::class), true)
        );
    }

    public function test_hits_configured_endpoints(): void
    {
        Http::fake();

        config(['webhook.cron_triggers' => [
            'test-product' => [
                'base_url' => 'https://example.com',
                'key' => 'secret123',
                'endpoints' => ['/cron', '/cron/reports'],
                'stagger_seconds' => 0,
                'offset_seconds' => 0,
            ],
        ]]);

        CronTrigger::run();

        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/cron?key=secret123'));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/cron/reports?key=secret123'));
    }

    public function test_skips_product_with_no_base_url(): void
    {
        Http::fake();

        config(['webhook.cron_triggers' => [
            'disabled-product' => [
                'base_url' => null,
                'key' => 'secret',
                'endpoints' => ['/cron'],
                'stagger_seconds' => 0,
                'offset_seconds' => 0,
            ],
        ]]);

        CronTrigger::run();

        Http::assertSentCount(0);
    }

    public function test_logs_failures_gracefully(): void
    {
        Http::fake([
            '*' => Http::response('error', 500),
        ]);

        config(['webhook.cron_triggers' => [
            'failing-product' => [
                'base_url' => 'https://broken.example.com',
                'key' => 'key',
                'endpoints' => ['/cron'],
                'stagger_seconds' => 0,
                'offset_seconds' => 0,
            ],
        ]]);

        // Should not throw
        CronTrigger::run();

        Http::assertSentCount(1);
    }

    public function test_handles_empty_config(): void
    {
        Http::fake();
        config(['webhook.cron_triggers' => []]);

        CronTrigger::run();

        Http::assertSentCount(0);
    }
}
