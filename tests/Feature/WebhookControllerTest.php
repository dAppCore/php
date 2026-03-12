<?php

declare(strict_types=1);

namespace Core\Tests\Feature;

use Core\Tests\TestCase;
use Core\Webhook\WebhookCall;
use Core\Webhook\WebhookReceived;
use Core\Webhook\WebhookVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

class WebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }

    protected function defineRoutes($router): void
    {
        $router->post('/webhooks/{source}', [\Core\Webhook\WebhookController::class, 'handle'])
            ->where('source', '[a-z0-9\-]+');
    }

    public function test_stores_webhook_call(): void
    {
        $response = $this->postJson('/webhooks/altum-biolinks', [
            'type' => 'link.created',
            'data' => ['id' => 42],
        ]);

        $response->assertOk();

        $call = WebhookCall::first();
        $this->assertNotNull($call);
        $this->assertSame('altum-biolinks', $call->source);
        $this->assertSame(['type' => 'link.created', 'data' => ['id' => 42]], $call->payload);
        $this->assertNull($call->processed_at);
    }

    public function test_captures_headers(): void
    {
        $this->postJson('/webhooks/stripe', ['type' => 'invoice.paid'], [
            'Webhook-Id' => 'msg_abc123',
            'Webhook-Timestamp' => '1234567890',
        ]);

        $call = WebhookCall::first();
        $this->assertArrayHasKey('webhook-id', $call->headers);
    }

    public function test_fires_webhook_received_event(): void
    {
        Event::fake([WebhookReceived::class]);

        $this->postJson('/webhooks/altum-biolinks', ['type' => 'test']);

        Event::assertDispatched(WebhookReceived::class, function ($event) {
            return $event->source === 'altum-biolinks' && ! empty($event->callId);
        });
    }

    public function test_extracts_event_type_from_payload(): void
    {
        $this->postJson('/webhooks/stripe', ['type' => 'invoice.paid']);

        $this->assertSame('invoice.paid', WebhookCall::first()->event_type);
    }

    public function test_handles_empty_payload(): void
    {
        $response = $this->postJson('/webhooks/test', []);

        $response->assertOk();
        $this->assertCount(1, WebhookCall::all());
    }

    public function test_signature_valid_null_when_no_verifier(): void
    {
        $this->postJson('/webhooks/unknown-source', ['data' => 1]);

        $this->assertNull(WebhookCall::first()->signature_valid);
    }

    public function test_signature_verified_when_verifier_registered(): void
    {
        $verifier = new class implements WebhookVerifier
        {
            public function verify(Request $request, string $secret): bool
            {
                return $request->header('webhook-signature') === 'valid';
            }
        };

        $this->app->instance('webhook.verifier.test-source', $verifier);
        $this->app['config']->set('webhook.secrets.test-source', 'test-secret');

        $this->postJson('/webhooks/test-source', ['data' => 1], [
            'Webhook-Signature' => 'valid',
        ]);

        $this->assertTrue(WebhookCall::first()->signature_valid);
    }

    public function test_signature_invalid_still_stores_call(): void
    {
        $verifier = new class implements WebhookVerifier
        {
            public function verify(Request $request, string $secret): bool
            {
                return false;
            }
        };

        $this->app->instance('webhook.verifier.test-source', $verifier);
        $this->app['config']->set('webhook.secrets.test-source', 'test-secret');

        $this->postJson('/webhooks/test-source', ['data' => 1]);

        $call = WebhookCall::first();
        $this->assertNotNull($call);
        $this->assertFalse($call->signature_valid);
    }

    public function test_source_is_sanitised(): void
    {
        $response = $this->postJson('/webhooks/valid-source-123', ['data' => 1]);
        $response->assertOk();

        $response = $this->postJson('/webhooks/invalid source!', ['data' => 1]);
        $response->assertStatus(404);
    }
}
