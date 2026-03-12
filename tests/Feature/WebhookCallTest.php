<?php

declare(strict_types=1);

namespace Core\Tests\Feature;

use Core\Tests\TestCase;
use Core\Webhook\WebhookCall;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WebhookCallTest extends TestCase
{
    use RefreshDatabase;

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }

    public function test_create_webhook_call(): void
    {
        $call = WebhookCall::create([
            'source' => 'altum-biolinks',
            'event_type' => 'link.created',
            'headers' => ['webhook-id' => 'abc123'],
            'payload' => ['type' => 'link.created', 'data' => ['id' => 1]],
        ]);

        $this->assertNotNull($call->id);
        $this->assertSame('altum-biolinks', $call->source);
        $this->assertSame('link.created', $call->event_type);
        $this->assertIsArray($call->headers);
        $this->assertIsArray($call->payload);
        $this->assertNull($call->signature_valid);
        $this->assertNull($call->processed_at);
    }

    public function test_unprocessed_scope(): void
    {
        WebhookCall::create([
            'source' => 'stripe',
            'headers' => [],
            'payload' => ['type' => 'invoice.paid'],
        ]);

        WebhookCall::create([
            'source' => 'stripe',
            'headers' => [],
            'payload' => ['type' => 'invoice.created'],
            'processed_at' => now(),
        ]);

        $unprocessed = WebhookCall::unprocessed()->get();
        $this->assertCount(1, $unprocessed);
        $this->assertSame('invoice.paid', $unprocessed->first()->payload['type']);
    }

    public function test_for_source_scope(): void
    {
        WebhookCall::create(['source' => 'stripe', 'headers' => [], 'payload' => []]);
        WebhookCall::create(['source' => 'altum-biolinks', 'headers' => [], 'payload' => []]);

        $this->assertCount(1, WebhookCall::forSource('stripe')->get());
        $this->assertCount(1, WebhookCall::forSource('altum-biolinks')->get());
    }

    public function test_mark_processed(): void
    {
        $call = WebhookCall::create([
            'source' => 'test',
            'headers' => [],
            'payload' => [],
        ]);

        $this->assertNull($call->processed_at);

        $call->markProcessed();

        $this->assertNotNull($call->fresh()->processed_at);
    }

    public function test_signature_valid_is_nullable_boolean(): void
    {
        $call = WebhookCall::create([
            'source' => 'test',
            'headers' => [],
            'payload' => [],
            'signature_valid' => false,
        ]);

        $this->assertFalse($call->signature_valid);
    }
}
