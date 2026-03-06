<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

use Core\Events\AdminPanelBooting;
use Core\LazyModuleListener;
use Core\ModuleScanner;
use Illuminate\Support\ServiceProvider;

// TestCase already configured in tests/Pest.php for Feature folders

describe('ModuleScanner integration', function () {
    it('scans the Mod directory and finds modules', function () {
        $scanner = new ModuleScanner;
        $result = $scanner->scan([app_path('Mod')]);

        // Should find multiple events with listeners
        expect($result)->toBeArray();
        expect(count($result))->toBeGreaterThan(0);

        // Common events should have listeners
        $commonEvents = [
            \Core\Events\AdminPanelBooting::class,
            \Core\Events\WebRoutesRegistering::class,
        ];

        foreach ($commonEvents as $event) {
            expect($result)->toHaveKey($event);
        }
    });

    it('returns event => [module => method] structure', function () {
        $scanner = new ModuleScanner;
        $result = $scanner->scan([app_path('Mod')]);

        foreach ($result as $event => $listeners) {
            expect($listeners)->toBeArray();
            foreach ($listeners as $moduleClass => $method) {
                expect($moduleClass)->toBeString();
                expect($method)->toBeString();
                expect(class_exists($moduleClass))->toBeTrue("Class {$moduleClass} should exist");
            }
        }
    });

    it('finds at least 10 modules with listeners', function () {
        $scanner = new ModuleScanner;
        $result = $scanner->scan([app_path('Mod')]);

        // Collect unique modules
        $modules = [];
        foreach ($result as $listeners) {
            foreach ($listeners as $moduleClass => $method) {
                $modules[$moduleClass] = true;
            }
        }

        expect(count($modules))->toBeGreaterThanOrEqual(10);
    });
});

describe('LazyModuleListener integration', function () {
    it('resolves ServiceProvider subclasses with app injection', function () {
        $listener = new LazyModuleListener(
            TestIntegrationServiceProvider::class,
            'onEvent'
        );

        TestIntegrationServiceProvider::$called = false;
        TestIntegrationServiceProvider::$hadApp = false;

        $event = new AdminPanelBooting;
        $listener($event);

        expect(TestIntegrationServiceProvider::$called)->toBeTrue();
        expect(TestIntegrationServiceProvider::$hadApp)->toBeTrue();
    });

    it('can invoke real module methods', function () {
        // Pick a real module
        $scanner = new ModuleScanner;
        $result = $scanner->scan([app_path('Mod')]);

        // Find a module that listens to AdminPanelBooting
        expect($result)->toHaveKey(AdminPanelBooting::class);

        $listeners = $result[AdminPanelBooting::class];
        $moduleClass = array_key_first($listeners);
        $method = $listeners[$moduleClass];

        // Create a lazy listener and invoke it
        $listener = new LazyModuleListener($moduleClass, $method);

        // Should not throw - the real module should handle the event
        $event = new AdminPanelBooting;
        $listener($event);

        // If we get here without exception, the listener worked
        expect(true)->toBeTrue();
    });
});

// Test fixture for ServiceProvider integration
class TestIntegrationServiceProvider extends ServiceProvider
{
    public static bool $called = false;

    public static bool $hadApp = false;

    public function onEvent(object $event): void
    {
        self::$called = true;
        self::$hadApp = $this->app !== null;
    }
}
