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
        $registry = new ModuleRegistry(new ModuleScanner());

        $this->assertFalse($registry->isRegistered());
    }

    public function test_registry_marks_as_registered_after_register(): void
    {
        $registry = new ModuleRegistry(new ModuleScanner());

        $registry->register([]);

        $this->assertTrue($registry->isRegistered());
    }

    public function test_registry_only_registers_once(): void
    {
        $registry = new ModuleRegistry(new ModuleScanner());

        $registry->register([]);
        $registry->register([$this->getFixturePath('Mod')]);

        // Should still be empty since it only registers once
        $this->assertEmpty($registry->getMappings());
    }

    public function test_get_mappings_returns_array(): void
    {
        $registry = new ModuleRegistry(new ModuleScanner());

        $this->assertIsArray($registry->getMappings());
    }

    public function test_get_listeners_for_returns_empty_for_unknown_event(): void
    {
        $registry = new ModuleRegistry(new ModuleScanner());

        $result = $registry->getListenersFor('Unknown\\Event');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_get_events_returns_array(): void
    {
        $registry = new ModuleRegistry(new ModuleScanner());
        $registry->register([]);

        $this->assertIsArray($registry->getEvents());
    }

    public function test_get_modules_returns_array(): void
    {
        $registry = new ModuleRegistry(new ModuleScanner());
        $registry->register([]);

        $this->assertIsArray($registry->getModules());
    }

    public function test_register_wires_lazy_listeners(): void
    {
        $registry = new ModuleRegistry(new ModuleScanner());
        $registry->register([$this->getFixturePath('Mod')]);

        $mappings = $registry->getMappings();

        $this->assertArrayHasKey(WebRoutesRegistering::class, $mappings);
        $this->assertArrayHasKey('Mod\\Example\\Boot', $mappings[WebRoutesRegistering::class]);
    }

    public function test_get_listeners_for_returns_module_mappings(): void
    {
        $registry = new ModuleRegistry(new ModuleScanner());
        $registry->register([$this->getFixturePath('Mod')]);

        $listeners = $registry->getListenersFor(WebRoutesRegistering::class);

        $this->assertArrayHasKey('Mod\\Example\\Boot', $listeners);
        $this->assertEquals('onWebRoutes', $listeners['Mod\\Example\\Boot']['method']);
    }

    public function test_get_events_returns_registered_events(): void
    {
        $registry = new ModuleRegistry(new ModuleScanner());
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
        $registry = new ModuleRegistry(new ModuleScanner());
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
        $registry = new ModuleRegistry(new ModuleScanner());
        $registry->register([$this->getFixturePath('Mod')]);

        // Fire the event and verify the module receives it
        $event = new WebRoutesRegistering();
        event($event);

        // The Example module registers views
        $this->assertNotEmpty($event->viewRequests());
    }

    /**
     * The vendor registration path: a class registers itself by name.
     *
     * No path, no directory convention, no guess — the Boot class already knows
     * what it is called.
     */
    public function test_register_class_wires_a_boot_class_by_name(): void
    {
        $registry = new ModuleRegistry(new ModuleScanner());

        $registry->registerClass(\Core\Tests\Fixtures\Mod\Displaced\Boot::class);

        $this->assertContains(
            \Core\Tests\Fixtures\Mod\Displaced\Boot::class,
            $registry->getModules(),
        );
        $this->assertArrayHasKey(
            \Core\Tests\Fixtures\Mod\Displaced\Boot::class,
            $registry->getListenersFor(WebRoutesRegistering::class),
        );
    }

    /**
     * The handler actually runs when the event fires — registering is not the
     * same claim as being called, which is the whole reason this method exists.
     */
    public function test_register_class_listener_runs_when_the_event_fires(): void
    {
        $registry = new ModuleRegistry(new ModuleScanner());
        $registry->registerClass(\Core\Tests\Fixtures\Mod\Displaced\Boot::class);

        $event = new WebRoutesRegistering();
        Event::dispatch($event);

        $namespaces = array_map(fn (array $request): string => $request[0], $event->viewRequests());
        $this->assertContains('displaced', $namespaces);
    }

    /**
     * A package that is both scanned and self-registering must not run twice.
     */
    public function test_register_class_is_idempotent(): void
    {
        $registry = new ModuleRegistry(new ModuleScanner());

        $registry->registerClass(\Core\Tests\Fixtures\Mod\Displaced\Boot::class);
        $registry->registerClass(\Core\Tests\Fixtures\Mod\Displaced\Boot::class);

        $event = new WebRoutesRegistering();
        Event::dispatch($event);

        $displaced = array_filter(
            $event->viewRequests(),
            fn (array $request): bool => $request[0] === 'displaced',
        );

        $this->assertCount(1, $displaced, 'the handler ran more than once');
    }

    /**
     * A class with no $listens registers nothing rather than erroring.
     */
    public function test_register_class_ignores_a_class_without_listens(): void
    {
        $registry = new ModuleRegistry(new ModuleScanner());

        $registry->registerClass(\Mod\NoListens\Boot::class);

        $this->assertSame([], $registry->getModules());
    }
}
