<?php

declare(strict_types=1);

namespace Core\Tests\Feature;

use Core\Events\FrameworkBooted;
use Core\Events\WebRoutesRegistering;
use Core\ModuleRegistry;
use Core\ModuleScanner;
use Core\Tests\TestCase;
use Illuminate\Support\Facades\Event;

class ModuleRegistryTest extends TestCase
{
    public function test_registry_starts_unregistered(): void
    {
        $registry = new ModuleRegistry(new ModuleScanner);

        $this->assertFalse($registry->isRegistered());
    }

    public function test_registry_marks_as_registered_after_register(): void
    {
        $registry = new ModuleRegistry(new ModuleScanner);

        $registry->register([]);

        $this->assertTrue($registry->isRegistered());
    }

    public function test_registry_only_registers_once(): void
    {
        $registry = new ModuleRegistry(new ModuleScanner);

        $registry->register([]);
        $registry->register([$this->getFixturePath('Mod')]);

        // Should still be empty since it only registers once
        $this->assertEmpty($registry->getMappings());
    }

    public function test_get_mappings_returns_array(): void
    {
        $registry = new ModuleRegistry(new ModuleScanner);

        $this->assertIsArray($registry->getMappings());
    }

    public function test_get_listeners_for_returns_empty_for_unknown_event(): void
    {
        $registry = new ModuleRegistry(new ModuleScanner);

        $result = $registry->getListenersFor('Unknown\\Event');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_get_events_returns_array(): void
    {
        $registry = new ModuleRegistry(new ModuleScanner);
        $registry->register([]);

        $this->assertIsArray($registry->getEvents());
    }

    public function test_get_modules_returns_array(): void
    {
        $registry = new ModuleRegistry(new ModuleScanner);
        $registry->register([]);

        $this->assertIsArray($registry->getModules());
    }

    public function test_register_wires_lazy_listeners(): void
    {
        $registry = new ModuleRegistry(new ModuleScanner);
        $registry->register([$this->getFixturePath('Mod')]);

        $mappings = $registry->getMappings();

        $this->assertArrayHasKey(WebRoutesRegistering::class, $mappings);
        $this->assertArrayHasKey('Mod\\Example\\Boot', $mappings[WebRoutesRegistering::class]);
    }

    public function test_get_listeners_for_returns_module_mappings(): void
    {
        $registry = new ModuleRegistry(new ModuleScanner);
        $registry->register([$this->getFixturePath('Mod')]);

        $listeners = $registry->getListenersFor(WebRoutesRegistering::class);

        $this->assertArrayHasKey('Mod\\Example\\Boot', $listeners);
        $this->assertEquals('onWebRoutes', $listeners['Mod\\Example\\Boot']['method']);
    }

    public function test_get_events_returns_registered_events(): void
    {
        $registry = new ModuleRegistry(new ModuleScanner);
        $registry->register([
            $this->getFixturePath('Mod'),
            $this->getFixturePath('Plug'),
        ]);

        $events = $registry->getEvents();

        $this->assertContains(WebRoutesRegistering::class, $events);
        $this->assertContains(FrameworkBooted::class, $events);
    }

    public function test_get_modules_returns_registered_modules(): void
    {
        $registry = new ModuleRegistry(new ModuleScanner);
        $registry->register([
            $this->getFixturePath('Mod'),
            $this->getFixturePath('Plug'),
        ]);

        $modules = $registry->getModules();

        $this->assertContains('Mod\\Example\\Boot', $modules);
        $this->assertContains('Plug\\TestPlugin\\Boot', $modules);
    }

    public function test_register_fires_events_to_listeners(): void
    {
        $registry = new ModuleRegistry(new ModuleScanner);
        $registry->register([$this->getFixturePath('Mod')]);

        // Fire the event and verify the module receives it
        $event = new WebRoutesRegistering;
        event($event);

        // The Example module registers views
        $this->assertNotEmpty($event->viewRequests());
    }
}
