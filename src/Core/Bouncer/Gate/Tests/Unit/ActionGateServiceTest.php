<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Bouncer\Gate\Tests\Unit;

use Core\Bouncer\Gate\ActionGateService;
use Core\Bouncer\Gate\Attributes\Action;
use Illuminate\Routing\Route;
use PHPUnit\Framework\TestCase;

class ActionGateServiceTest extends TestCase
{
    protected ActionGateService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ActionGateService;
    }

    // =========================================================================
    // Auto-Resolution Tests (via uses action string)
    // =========================================================================

    public function test_auto_resolves_simple_controller(): void
    {
        $route = new Route(['GET'], '/products', ['uses' => 'ProductController@index']);

        $result = $this->service->resolveAction($route);

        $this->assertEquals('product.index', $result['action']);
    }

    public function test_auto_resolves_nested_namespace(): void
    {
        $route = new Route(['POST'], '/admin/users', ['uses' => 'Admin\\UserController@store']);

        $result = $this->service->resolveAction($route);

        $this->assertEquals('admin.user.store', $result['action']);
    }

    public function test_auto_resolves_deeply_nested_namespace(): void
    {
        $route = new Route(['GET'], '/api/v1/orders', ['uses' => 'Api\\V1\\OrderController@show']);

        $result = $this->service->resolveAction($route);

        $this->assertEquals('api.v1.order.show', $result['action']);
    }

    public function test_auto_resolves_pascal_case_controller(): void
    {
        $route = new Route(['GET'], '/user-profiles', ['uses' => 'UserProfileController@index']);

        $result = $this->service->resolveAction($route);

        $this->assertEquals('user_profile.index', $result['action']);
    }

    public function test_filters_common_namespace_prefixes(): void
    {
        $route = new Route(['GET'], '/test', ['uses' => 'App\\Http\\Controllers\\TestController@index']);

        $result = $this->service->resolveAction($route);

        // Should not include 'app', 'http', 'controllers'
        $this->assertEquals('test.index', $result['action']);
    }

    // =========================================================================
    // Route Action Override Tests
    // =========================================================================

    public function test_route_action_takes_precedence(): void
    {
        $route = new Route(['GET'], '/products', ['uses' => 'ProductController@index']);
        $route->setAction(array_merge($route->getAction(), [
            'bouncer_action' => 'catalog.list',
        ]));

        $result = $this->service->resolveAction($route);

        $this->assertEquals('catalog.list', $result['action']);
    }

    public function test_route_scope_is_preserved(): void
    {
        $route = new Route(['DELETE'], '/products/1', ['uses' => 'ProductController@destroy']);
        $route->setAction(array_merge($route->getAction(), [
            'bouncer_action' => 'product.delete',
            'bouncer_scope' => 'product',
        ]));

        $result = $this->service->resolveAction($route);

        $this->assertEquals('product.delete', $result['action']);
        $this->assertEquals('product', $result['scope']);
    }

    // =========================================================================
    // Closure/Named Route Tests
    // =========================================================================

    public function test_closure_routes_use_uri_fallback(): void
    {
        $route = new Route(['GET'], '/hello', fn () => 'hello');

        $result = $this->service->resolveAction($route);

        $this->assertEquals('route.hello', $result['action']);
    }

    public function test_named_closure_routes_use_name(): void
    {
        $route = new Route(['GET'], '/hello', fn () => 'hello');
        $route->name('greeting.hello');

        $result = $this->service->resolveAction($route);

        $this->assertEquals('route.greeting.hello', $result['action']);
    }

    // =========================================================================
    // Caching Tests
    // =========================================================================

    public function test_caches_resolved_actions(): void
    {
        $route = new Route(['GET'], '/products', ['uses' => 'ProductController@index']);
        $route->name('products.index');

        // First call
        $result1 = $this->service->resolveAction($route);

        // Second call should use cache
        $result2 = $this->service->resolveAction($route);

        $this->assertEquals($result1, $result2);
    }

    public function test_clear_cache_works(): void
    {
        $route = new Route(['GET'], '/products', ['uses' => 'ProductController@index']);
        $route->name('products.index');

        $this->service->resolveAction($route);
        $this->service->clearCache();

        // Should not throw - just verify it works
        $result = $this->service->resolveAction($route);
        $this->assertNotEmpty($result['action']);
    }

    // =========================================================================
    // Guard Resolution Tests
    // =========================================================================

    public function test_resolves_admin_guard(): void
    {
        $route = new Route(['GET'], '/admin/dashboard', ['uses' => 'DashboardController@index']);
        $route->middleware('admin');

        $method = new \ReflectionMethod($this->service, 'resolveGuard');
        $method->setAccessible(true);

        $guard = $method->invoke($this->service, $route);

        $this->assertEquals('admin', $guard);
    }

    public function test_resolves_api_guard(): void
    {
        $route = new Route(['GET'], '/api/users', ['uses' => 'UserController@index']);
        $route->middleware('api');

        $method = new \ReflectionMethod($this->service, 'resolveGuard');
        $method->setAccessible(true);

        $guard = $method->invoke($this->service, $route);

        $this->assertEquals('api', $guard);
    }

    public function test_defaults_to_web_guard(): void
    {
        $route = new Route(['GET'], '/home', ['uses' => 'HomeController@index']);

        $method = new \ReflectionMethod($this->service, 'resolveGuard');
        $method->setAccessible(true);

        $guard = $method->invoke($this->service, $route);

        $this->assertEquals('web', $guard);
    }

    // =========================================================================
    // Action Attribute Tests
    // =========================================================================

    public function test_action_attribute_stores_name(): void
    {
        $attribute = new Action('product.create');

        $this->assertEquals('product.create', $attribute->name);
        $this->assertNull($attribute->scope);
    }

    public function test_action_attribute_stores_scope(): void
    {
        $attribute = new Action('product.delete', scope: 'product');

        $this->assertEquals('product.delete', $attribute->name);
        $this->assertEquals('product', $attribute->scope);
    }

    // =========================================================================
    // Result Builder Tests
    // =========================================================================

    public function test_result_constants_are_defined(): void
    {
        $this->assertEquals('allowed', ActionGateService::RESULT_ALLOWED);
        $this->assertEquals('denied', ActionGateService::RESULT_DENIED);
        $this->assertEquals('training', ActionGateService::RESULT_TRAINING);
    }
}
