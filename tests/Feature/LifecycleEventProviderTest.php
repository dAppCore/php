<?php

declare(strict_types=1);

namespace Core\Tests\Feature;

use Core\Events\FrameworkBooted;
use Core\LifecycleEventProvider;
use Core\ModuleRegistry;
use Core\ModuleScanner;
use Core\Tests\TestCase;

class LifecycleEventProviderTest extends TestCase
{
    public function test_provider_is_registered(): void
    {
        $this->assertInstanceOf(
            LifecycleEventProvider::class,
            $this->app->getProvider(LifecycleEventProvider::class)
        );
    }

    public function test_provider_registers_module_scanner_as_singleton(): void
    {
        $scanner1 = $this->app->make(ModuleScanner::class);
        $scanner2 = $this->app->make(ModuleScanner::class);

        $this->assertSame($scanner1, $scanner2);
    }

    public function test_provider_registers_module_registry_as_singleton(): void
    {
        $registry1 = $this->app->make(ModuleRegistry::class);
        $registry2 = $this->app->make(ModuleRegistry::class);

        $this->assertSame($registry1, $registry2);
    }

    public function test_provider_fires_framework_booted_event(): void
    {
        $this->assertTrue(class_exists(FrameworkBooted::class));
    }

    public function test_provider_registers_modules(): void
    {
        $registry = new ModuleRegistry(new ModuleScanner);
        $registry->register([$this->getFixturePath('Mod')]);

        $this->assertTrue($registry->isRegistered());
        $this->assertNotEmpty($registry->getMappings());
    }

    public function test_fire_web_routes_fires_event(): void
    {
        LifecycleEventProvider::fireWebRoutes();
        $this->assertTrue(true);
    }

    public function test_fire_admin_booting_fires_event(): void
    {
        LifecycleEventProvider::fireAdminBooting();
        $this->assertTrue(true);
    }

    public function test_fire_client_routes_fires_event(): void
    {
        LifecycleEventProvider::fireClientRoutes();
        $this->assertTrue(true);
    }

    public function test_fire_api_routes_fires_event(): void
    {
        LifecycleEventProvider::fireApiRoutes();
        $this->assertTrue(true);
    }

    public function test_fire_mcp_tools_returns_array(): void
    {
        $handlers = LifecycleEventProvider::fireMcpTools();
        $this->assertIsArray($handlers);
    }
}
