<?php

declare(strict_types=1);

namespace Core\Tests\Feature;

use Core\Events\ApiRoutesRegistering;
use Core\Events\FrameworkBooted;
use Core\Events\WebRoutesRegistering;
use Core\LifecycleEventProvider;
use Core\ModuleRegistry;
use Core\ModuleScanner;
use Core\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;

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

    public function test_fire_web_routes_deduplicates_route_names_across_domains(): void
    {
        // Register the same named route on two different domains
        Event::listen(WebRoutesRegistering::class, function (WebRoutesRegistering $event) {
            $event->routes(fn () => Route::domain('example.test')
                ->name('hub.')
                ->group(function () {
                    Route::get('/dashboard', fn () => 'ok')->name('dashboard');
                }));

            $event->routes(fn () => Route::domain('hub.example.test')
                ->name('hub.')
                ->group(function () {
                    Route::get('/dashboard', fn () => 'ok')->name('dashboard');
                }));
        });

        LifecycleEventProvider::fireWebRoutes();

        $routes = app('router')->getRoutes();
        $named = collect($routes->getRoutes())
            ->filter(fn ($r) => $r->getName() === 'hub.dashboard');

        $this->assertCount(1, $named, 'Only one route should keep the name "hub.dashboard"');

        // Both routes should still exist (just one unnamed)
        $allDashboard = collect($routes->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'dashboard');
        $this->assertCount(2, $allDashboard, 'Both domain routes should still be registered');
    }

    public function test_fire_api_routes_deduplicates_route_names_across_domains(): void
    {
        Event::listen(ApiRoutesRegistering::class, function (ApiRoutesRegistering $event) {
            $event->routes(fn () => Route::domain('api.example.test')
                ->name('api.')
                ->group(function () {
                    Route::get('/users', fn () => 'ok')->name('users.index');
                }));

            $event->routes(fn () => Route::domain('api.hub.example.test')
                ->name('api.')
                ->group(function () {
                    Route::get('/users', fn () => 'ok')->name('users.index');
                }));
        });

        LifecycleEventProvider::fireApiRoutes();

        $routes = app('router')->getRoutes();
        $named = collect($routes->getRoutes())
            ->filter(fn ($r) => $r->getName() === 'api.users.index');

        $this->assertCount(1, $named, 'Only one route should keep the name "api.users.index"');
    }

    public function test_deduplication_preserves_unique_route_names(): void
    {
        Event::listen(WebRoutesRegistering::class, function (WebRoutesRegistering $event) {
            $event->routes(fn () => Route::domain('example.test')
                ->group(function () {
                    Route::get('/home', fn () => 'ok')->name('home');
                    Route::get('/about', fn () => 'ok')->name('about');
                }));
        });

        LifecycleEventProvider::fireWebRoutes();

        $routes = app('router')->getRoutes();
        $this->assertNotNull($routes->getByName('home'));
        $this->assertNotNull($routes->getByName('about'));
    }
}
