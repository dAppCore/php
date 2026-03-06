<?php

declare(strict_types=1);

namespace Core\Tests\Feature;

use Core\Events\AdminPanelBooting;
use Core\Events\ApiRoutesRegistering;
use Core\Events\ClientRoutesRegistering;
use Core\Events\ConsoleBooting;
use Core\Events\FrameworkBooted;
use Core\Events\MailSending;
use Core\Events\McpToolsRegistering;
use Core\Events\MediaRequested;
use Core\Events\QueueWorkerBooting;
use Core\Events\SearchRequested;
use Core\Events\WebRoutesRegistering;
use Core\Front\Mcp\Contracts\McpToolHandler;
use Core\Tests\TestCase;

// Test fixture implementing McpToolHandler
class TestMcpHandler implements McpToolHandler
{
    public static function schema(): array
    {
        return ['name' => 'test', 'description' => 'Test', 'inputSchema' => []];
    }

    public function handle(array $args, \Core\Front\Mcp\McpContext $context): array
    {
        return [];
    }
}

class TestMcpHandler2 implements McpToolHandler
{
    public static function schema(): array
    {
        return ['name' => 'test2', 'description' => 'Test 2', 'inputSchema' => []];
    }

    public function handle(array $args, \Core\Front\Mcp\McpContext $context): array
    {
        return [];
    }
}

class TestMcpHandler3 implements McpToolHandler
{
    public static function schema(): array
    {
        return ['name' => 'test3', 'description' => 'Test 3', 'inputSchema' => []];
    }

    public function handle(array $args, \Core\Front\Mcp\McpContext $context): array
    {
        return [];
    }
}

class LifecycleEventsTest extends TestCase
{
    public function test_web_routes_event_collects_route_requests(): void
    {
        $event = new WebRoutesRegistering;

        $event->routes(fn () => 'test');

        $this->assertCount(1, $event->routeRequests());
    }

    public function test_web_routes_event_collects_view_requests(): void
    {
        $event = new WebRoutesRegistering;

        $event->views('test', '/path/to/views');

        $requests = $event->viewRequests();
        $this->assertCount(1, $requests);
        $this->assertEquals(['test', '/path/to/views'], $requests[0]);
    }

    public function test_admin_event_collects_navigation_requests(): void
    {
        $event = new AdminPanelBooting;

        $event->navigation(['label' => 'Test', 'icon' => 'cog']);

        $this->assertCount(1, $event->navigationRequests());
    }

    public function test_api_event_collects_route_requests(): void
    {
        $event = new ApiRoutesRegistering;

        $event->routes(fn () => 'api-test');

        $this->assertCount(1, $event->routeRequests());
    }

    public function test_client_event_collects_livewire_requests(): void
    {
        $event = new ClientRoutesRegistering;

        $event->livewire('test-component', 'App\\Livewire\\TestComponent');

        $requests = $event->livewireRequests();
        $this->assertCount(1, $requests);
        $this->assertEquals(['test-component', 'App\\Livewire\\TestComponent'], $requests[0]);
    }

    public function test_console_event_collects_command_requests(): void
    {
        $event = new ConsoleBooting;

        $event->command('App\\Console\\Commands\\TestCommand');

        $this->assertCount(1, $event->commandRequests());
    }

    public function test_mcp_event_collects_handlers(): void
    {
        $event = new McpToolsRegistering;

        $event->handler(TestMcpHandler::class);

        $this->assertCount(1, $event->handlers());
        $this->assertEquals([TestMcpHandler::class], $event->handlers());
    }

    public function test_framework_booted_event_exists(): void
    {
        $event = new FrameworkBooted;

        $this->assertInstanceOf(FrameworkBooted::class, $event);
    }

    public function test_lifecycle_event_collects_middleware_requests(): void
    {
        $event = new WebRoutesRegistering;

        $event->middleware('custom', 'App\\Http\\Middleware\\Custom');

        $requests = $event->middlewareRequests();
        $this->assertCount(1, $requests);
        $this->assertEquals(['custom', 'App\\Http\\Middleware\\Custom'], $requests[0]);
    }

    public function test_lifecycle_event_collects_translation_requests(): void
    {
        $event = new WebRoutesRegistering;

        $event->translations('test', '/path/to/lang');

        $requests = $event->translationRequests();
        $this->assertCount(1, $requests);
        $this->assertEquals(['test', '/path/to/lang'], $requests[0]);
    }

    public function test_lifecycle_event_collects_policy_requests(): void
    {
        $event = new WebRoutesRegistering;

        $event->policy('App\\Models\\User', 'App\\Policies\\UserPolicy');

        $requests = $event->policyRequests();
        $this->assertCount(1, $requests);
        $this->assertEquals(['App\\Models\\User', 'App\\Policies\\UserPolicy'], $requests[0]);
    }

    public function test_lifecycle_event_collects_blade_component_requests(): void
    {
        $event = new WebRoutesRegistering;

        $event->bladeComponentPath('/path/to/components', 'custom');

        $requests = $event->bladeComponentRequests();
        $this->assertCount(1, $requests);
        $this->assertEquals(['/path/to/components', 'custom'], $requests[0]);
    }

    public function test_mcp_event_multiple_handlers_can_be_registered(): void
    {
        $event = new McpToolsRegistering;

        $event->handler(TestMcpHandler::class);
        $event->handler(TestMcpHandler2::class);
        $event->handler(TestMcpHandler3::class);

        $handlers = $event->handlers();
        $this->assertCount(3, $handlers);
        $this->assertContains(TestMcpHandler::class, $handlers);
        $this->assertContains(TestMcpHandler2::class, $handlers);
        $this->assertContains(TestMcpHandler3::class, $handlers);
    }

    public function test_mcp_event_handlers_returns_empty_when_none_registered(): void
    {
        $event = new McpToolsRegistering;

        $handlers = $event->handlers();
        $this->assertIsArray($handlers);
        $this->assertEmpty($handlers);
    }

    public function test_queue_worker_booting_event_exists(): void
    {
        $event = new QueueWorkerBooting;

        $this->assertInstanceOf(QueueWorkerBooting::class, $event);
    }

    public function test_mail_sending_event_collects_mailable_requests(): void
    {
        $event = new MailSending;

        $event->mailable('App\\Mail\\WelcomeEmail');
        $event->mailable('App\\Mail\\OrderConfirmation');

        $requests = $event->mailableRequests();
        $this->assertCount(2, $requests);
        $this->assertContains('App\\Mail\\WelcomeEmail', $requests);
        $this->assertContains('App\\Mail\\OrderConfirmation', $requests);
    }

    public function test_mail_sending_event_returns_empty_when_none_registered(): void
    {
        $event = new MailSending;

        $this->assertIsArray($event->mailableRequests());
        $this->assertEmpty($event->mailableRequests());
    }

    public function test_search_requested_event_collects_searchable_models(): void
    {
        $event = new SearchRequested;

        $event->searchable('App\\Models\\Product');
        $event->searchable('App\\Models\\Article');

        $requests = $event->searchableRequests();
        $this->assertCount(2, $requests);
        $this->assertContains('App\\Models\\Product', $requests);
        $this->assertContains('App\\Models\\Article', $requests);
    }

    public function test_search_requested_event_returns_empty_when_none_registered(): void
    {
        $event = new SearchRequested;

        $this->assertIsArray($event->searchableRequests());
        $this->assertEmpty($event->searchableRequests());
    }

    public function test_media_requested_event_collects_processor_requests(): void
    {
        $event = new MediaRequested;

        $event->processor('image', 'App\\Media\\ImageProcessor');
        $event->processor('video', 'App\\Media\\VideoProcessor');

        $requests = $event->processorRequests();
        $this->assertCount(2, $requests);
        $this->assertEquals('App\\Media\\ImageProcessor', $requests['image']);
        $this->assertEquals('App\\Media\\VideoProcessor', $requests['video']);
    }

    public function test_media_requested_event_returns_empty_when_none_registered(): void
    {
        $event = new MediaRequested;

        $this->assertIsArray($event->processorRequests());
        $this->assertEmpty($event->processorRequests());
    }

    public function test_media_requested_event_overwrites_processor_of_same_type(): void
    {
        $event = new MediaRequested;

        $event->processor('image', 'App\\Media\\OldProcessor');
        $event->processor('image', 'App\\Media\\NewProcessor');

        $requests = $event->processorRequests();
        $this->assertCount(1, $requests);
        $this->assertEquals('App\\Media\\NewProcessor', $requests['image']);
    }
}
