<?php

declare(strict_types=1);

namespace Core\Tests\Feature;

use Core\Events\EventAuditLog;
use Core\Events\WebRoutesRegistering;
use Core\LazyModuleListener;
use Core\Tests\TestCase;
use Illuminate\Support\ServiceProvider;

class LazyModuleListenerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        EventAuditLog::reset();
    }

    protected function tearDown(): void
    {
        EventAuditLog::reset();
        parent::tearDown();
    }

    public function test_listener_stores_module_class(): void
    {
        $listener = new LazyModuleListener(
            'App\\Mod\\Test\\Boot',
            'onWebRoutes'
        );

        $this->assertEquals('App\\Mod\\Test\\Boot', $listener->getModuleClass());
    }

    public function test_listener_stores_method(): void
    {
        $listener = new LazyModuleListener(
            'App\\Mod\\Test\\Boot',
            'onWebRoutes'
        );

        $this->assertEquals('onWebRoutes', $listener->getMethod());
    }

    public function test_listener_invokes_module_method(): void
    {
        // Create a test module class
        $moduleClass = new class
        {
            public bool $called = false;

            public function onWebRoutes(WebRoutesRegistering $event): void
            {
                $this->called = true;
            }
        };

        // Bind it to the container
        $this->app->instance($moduleClass::class, $moduleClass);

        $listener = new LazyModuleListener(
            $moduleClass::class,
            'onWebRoutes'
        );

        $event = new WebRoutesRegistering;
        $listener($event);

        $this->assertTrue($moduleClass->called);
    }

    public function test_handle_is_alias_for_invoke(): void
    {
        $moduleClass = new class
        {
            public int $callCount = 0;

            public function onWebRoutes(WebRoutesRegistering $event): void
            {
                $this->callCount++;
            }
        };

        $this->app->instance($moduleClass::class, $moduleClass);

        $listener = new LazyModuleListener(
            $moduleClass::class,
            'onWebRoutes'
        );

        $event = new WebRoutesRegistering;
        $listener->handle($event);

        $this->assertEquals(1, $moduleClass->callCount);
    }

    public function test_listener_caches_module_instance(): void
    {
        $moduleClass = new class
        {
            public int $callCount = 0;

            public function onWebRoutes(WebRoutesRegistering $event): void
            {
                $this->callCount++;
            }
        };

        $this->app->instance($moduleClass::class, $moduleClass);

        $listener = new LazyModuleListener(
            $moduleClass::class,
            'onWebRoutes'
        );

        $event = new WebRoutesRegistering;
        $listener($event);
        $listener($event);

        // Should use same instance, so callCount should be 2
        $this->assertEquals(2, $moduleClass->callCount);
    }

    public function test_listener_resolves_service_providers_correctly(): void
    {
        // Load the ServiceProvider fixture
        require_once $this->getFixturePath('Mod/ServiceProviderModule/Boot.php');

        $listener = new LazyModuleListener(
            \Mod\ServiceProviderModule\Boot::class,
            'onWebRoutes'
        );

        $event = new WebRoutesRegistering;
        $listener($event);

        // The ServiceProvider should have been resolved via resolveProvider
        // which ensures proper $app injection
        $this->assertTrue(true); // If we get here without error, it worked
    }

    public function test_listener_works_with_plain_classes(): void
    {
        $plainClass = new class
        {
            public bool $invoked = false;

            public function handle(WebRoutesRegistering $event): void
            {
                $this->invoked = true;
            }
        };

        $this->app->instance($plainClass::class, $plainClass);

        $listener = new LazyModuleListener(
            $plainClass::class,
            'handle'
        );

        $event = new WebRoutesRegistering;
        $listener($event);

        $this->assertTrue($plainClass->invoked);
    }

    public function test_listener_can_modify_event(): void
    {
        $moduleClass = new class
        {
            public function onWebRoutes(WebRoutesRegistering $event): void
            {
                $event->views('test-namespace', '/test/path');
            }
        };

        $this->app->instance($moduleClass::class, $moduleClass);

        $listener = new LazyModuleListener(
            $moduleClass::class,
            'onWebRoutes'
        );

        $event = new WebRoutesRegistering;
        $listener($event);

        $this->assertCount(1, $event->viewRequests());
    }

    public function test_listener_records_to_audit_log_when_enabled(): void
    {
        EventAuditLog::enable();

        $moduleClass = new class
        {
            public function onWebRoutes(WebRoutesRegistering $event): void
            {
                // Handler executes successfully
            }
        };

        $this->app->instance($moduleClass::class, $moduleClass);

        $listener = new LazyModuleListener(
            $moduleClass::class,
            'onWebRoutes'
        );

        $event = new WebRoutesRegistering;
        $listener($event);

        $entries = EventAuditLog::entries();

        $this->assertCount(1, $entries);
        $this->assertEquals(WebRoutesRegistering::class, $entries[0]['event']);
        $this->assertEquals($moduleClass::class, $entries[0]['handler']);
        $this->assertFalse($entries[0]['failed']);
    }

    public function test_listener_records_failures_to_audit_log(): void
    {
        EventAuditLog::enable();

        $moduleClass = new class
        {
            public function onWebRoutes(WebRoutesRegistering $event): void
            {
                throw new \RuntimeException('Handler failed');
            }
        };

        $this->app->instance($moduleClass::class, $moduleClass);

        $listener = new LazyModuleListener(
            $moduleClass::class,
            'onWebRoutes'
        );

        $event = new WebRoutesRegistering;

        try {
            $listener($event);
        } catch (\RuntimeException) {
            // Expected
        }

        $failures = EventAuditLog::failures();

        $this->assertCount(1, $failures);
        $this->assertEquals('Handler failed', $failures[0]['error']);
    }

    public function test_listener_rethrows_exceptions_after_recording(): void
    {
        EventAuditLog::enable();

        $moduleClass = new class
        {
            public function onWebRoutes(WebRoutesRegistering $event): void
            {
                throw new \RuntimeException('Handler failed');
            }
        };

        $this->app->instance($moduleClass::class, $moduleClass);

        $listener = new LazyModuleListener(
            $moduleClass::class,
            'onWebRoutes'
        );

        $event = new WebRoutesRegistering;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Handler failed');

        $listener($event);
    }
}
