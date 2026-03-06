<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Bouncer\Gate\Tests\Feature;

use Core\Bouncer\Gate\ActionGateService;
use Core\Bouncer\Gate\Attributes\Action;
use Core\Bouncer\Gate\Models\ActionPermission;
use Core\Bouncer\Gate\Models\ActionRequest;
use Core\Bouncer\Gate\RouteActionMacro;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Orchestra\Testbench\TestCase;

class ActionGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Register route macros
        RouteActionMacro::register();

        // Run migrations
        $this->loadMigrationsFrom(__DIR__.'/../../Migrations');
    }

    protected function getPackageProviders($app): array
    {
        return [
            \Core\Bouncer\Gate\Boot::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('core.bouncer.enabled', true);
        $app['config']->set('core.bouncer.training_mode', false);
    }

    // =========================================================================
    // ActionPermission Model Tests
    // =========================================================================

    public function test_action_permission_can_be_created(): void
    {
        $permission = ActionPermission::create([
            'action' => 'product.create',
            'guard' => 'web',
            'allowed' => true,
            'source' => ActionPermission::SOURCE_MANUAL,
        ]);

        $this->assertDatabaseHas('core_action_permissions', [
            'action' => 'product.create',
            'guard' => 'web',
            'allowed' => true,
        ]);
    }

    public function test_is_allowed_returns_true_for_permitted_action(): void
    {
        ActionPermission::create([
            'action' => 'product.view',
            'guard' => 'web',
            'allowed' => true,
            'source' => ActionPermission::SOURCE_SEEDED,
        ]);

        $this->assertTrue(ActionPermission::isAllowed('product.view', 'web'));
    }

    public function test_is_allowed_returns_false_for_non_existent_action(): void
    {
        $this->assertFalse(ActionPermission::isAllowed('unknown.action', 'web'));
    }

    public function test_is_allowed_returns_false_for_denied_action(): void
    {
        ActionPermission::create([
            'action' => 'product.delete',
            'guard' => 'web',
            'allowed' => false,
            'source' => ActionPermission::SOURCE_MANUAL,
        ]);

        $this->assertFalse(ActionPermission::isAllowed('product.delete', 'web'));
    }

    public function test_is_allowed_respects_guard(): void
    {
        ActionPermission::create([
            'action' => 'product.create',
            'guard' => 'admin',
            'allowed' => true,
            'source' => ActionPermission::SOURCE_SEEDED,
        ]);

        $this->assertTrue(ActionPermission::isAllowed('product.create', 'admin'));
        $this->assertFalse(ActionPermission::isAllowed('product.create', 'web'));
    }

    public function test_is_allowed_respects_role(): void
    {
        ActionPermission::create([
            'action' => 'product.create',
            'guard' => 'web',
            'role' => 'editor',
            'allowed' => true,
            'source' => ActionPermission::SOURCE_SEEDED,
        ]);

        $this->assertTrue(ActionPermission::isAllowed('product.create', 'web', 'editor'));
        $this->assertFalse(ActionPermission::isAllowed('product.create', 'web', 'viewer'));
    }

    public function test_null_role_permission_allows_any_role(): void
    {
        ActionPermission::create([
            'action' => 'product.view',
            'guard' => 'web',
            'role' => null,
            'allowed' => true,
            'source' => ActionPermission::SOURCE_SEEDED,
        ]);

        $this->assertTrue(ActionPermission::isAllowed('product.view', 'web', 'admin'));
        $this->assertTrue(ActionPermission::isAllowed('product.view', 'web', 'editor'));
        $this->assertTrue(ActionPermission::isAllowed('product.view', 'web', null));
    }

    public function test_train_creates_and_allows_action(): void
    {
        $permission = ActionPermission::train(
            action: 'order.refund',
            guard: 'admin',
            role: 'manager',
            route: '/admin/orders/1/refund',
            trainedBy: 1
        );

        $this->assertTrue($permission->allowed);
        $this->assertEquals(ActionPermission::SOURCE_TRAINED, $permission->source);
        $this->assertEquals('/admin/orders/1/refund', $permission->trained_route);
        $this->assertEquals(1, $permission->trained_by);
        $this->assertNotNull($permission->trained_at);
    }

    public function test_revoke_denies_action(): void
    {
        ActionPermission::train('product.delete', 'web');

        $result = ActionPermission::revoke('product.delete', 'web');

        $this->assertTrue($result);
        $this->assertFalse(ActionPermission::isAllowed('product.delete', 'web'));
    }

    // =========================================================================
    // ActionRequest Model Tests
    // =========================================================================

    public function test_action_request_can_be_logged(): void
    {
        $request = ActionRequest::log(
            method: 'POST',
            route: '/products',
            action: 'product.create',
            guard: 'web',
            status: ActionRequest::STATUS_ALLOWED,
            userId: 1,
            ipAddress: '127.0.0.1'
        );

        $this->assertDatabaseHas('core_action_requests', [
            'method' => 'POST',
            'action' => 'product.create',
            'status' => 'allowed',
        ]);
    }

    public function test_pending_returns_pending_requests(): void
    {
        ActionRequest::log('GET', '/test', 'test.action', 'web', ActionRequest::STATUS_PENDING);
        ActionRequest::log('POST', '/test', 'test.create', 'web', ActionRequest::STATUS_ALLOWED);

        $pending = ActionRequest::pending();

        $this->assertCount(1, $pending);
        $this->assertEquals('test.action', $pending->first()->action);
    }

    public function test_denied_actions_summary_groups_by_action(): void
    {
        ActionRequest::log('GET', '/a', 'product.view', 'web', ActionRequest::STATUS_DENIED);
        ActionRequest::log('GET', '/b', 'product.view', 'web', ActionRequest::STATUS_DENIED);
        ActionRequest::log('POST', '/c', 'product.create', 'web', ActionRequest::STATUS_DENIED);

        $summary = ActionRequest::deniedActionsSummary();

        $this->assertArrayHasKey('product.view', $summary);
        $this->assertEquals(2, $summary['product.view']['count']);
        $this->assertArrayHasKey('product.create', $summary);
        $this->assertEquals(1, $summary['product.create']['count']);
    }

    // =========================================================================
    // ActionGateService Tests
    // =========================================================================

    public function test_service_allows_permitted_action(): void
    {
        ActionPermission::train('product.index', 'web');

        $service = new ActionGateService;
        $route = $this->createMockRoute('ProductController@index', 'web');
        $request = $this->createMockRequest($route);

        $result = $service->check($request);

        $this->assertEquals(ActionGateService::RESULT_ALLOWED, $result['result']);
    }

    public function test_service_denies_unknown_action_in_production(): void
    {
        config(['core.bouncer.training_mode' => false]);

        $service = new ActionGateService;
        $route = $this->createMockRoute('ProductController@store', 'web');
        $request = $this->createMockRequest($route);

        $result = $service->check($request);

        $this->assertEquals(ActionGateService::RESULT_DENIED, $result['result']);
    }

    public function test_service_returns_training_in_training_mode(): void
    {
        config(['core.bouncer.training_mode' => true]);

        $service = new ActionGateService;
        $route = $this->createMockRoute('OrderController@refund', 'web');
        $request = $this->createMockRequest($route);

        $result = $service->check($request);

        $this->assertEquals(ActionGateService::RESULT_TRAINING, $result['result']);
    }

    public function test_service_logs_request(): void
    {
        ActionPermission::train('product.show', 'web');

        $service = new ActionGateService;
        $route = $this->createMockRoute('ProductController@show', 'web');
        $request = $this->createMockRequest($route);

        $service->check($request);

        $this->assertDatabaseHas('core_action_requests', [
            'action' => 'product.show',
            'status' => 'allowed',
        ]);
    }

    // =========================================================================
    // Action Resolution Tests
    // =========================================================================

    public function test_resolves_action_from_route_action(): void
    {
        $service = new ActionGateService;

        $route = new Route(['GET'], '/products', ['uses' => 'ProductController@index']);
        $route->setAction(array_merge($route->getAction(), [
            'bouncer_action' => 'products.list',
            'bouncer_scope' => 'catalog',
        ]));

        $result = $service->resolveAction($route);

        $this->assertEquals('products.list', $result['action']);
        $this->assertEquals('catalog', $result['scope']);
    }

    public function test_auto_resolves_action_from_controller_method(): void
    {
        $service = new ActionGateService;

        $route = new Route(['POST'], '/products', ['uses' => 'ProductController@store']);

        $result = $service->resolveAction($route);

        $this->assertEquals('product.store', $result['action']);
    }

    public function test_auto_resolves_namespaced_controller(): void
    {
        $service = new ActionGateService;

        $route = new Route(['GET'], '/admin/users', ['uses' => 'Admin\\UserController@index']);

        $result = $service->resolveAction($route);

        $this->assertEquals('admin.user.index', $result['action']);
    }

    // =========================================================================
    // Route Macro Tests
    // =========================================================================

    public function test_route_action_macro_sets_action(): void
    {
        $route = RouteFacade::get('/test', fn () => 'test')
            ->action('custom.action');

        $this->assertEquals('custom.action', $route->getAction('bouncer_action'));
    }

    public function test_route_action_macro_sets_scope(): void
    {
        $route = RouteFacade::get('/test/{id}', fn () => 'test')
            ->action('resource.view', 'resource');

        $this->assertEquals('resource.view', $route->getAction('bouncer_action'));
        $this->assertEquals('resource', $route->getAction('bouncer_scope'));
    }

    public function test_route_bypass_gate_macro(): void
    {
        $route = RouteFacade::get('/login', fn () => 'login')
            ->bypassGate();

        $this->assertTrue($route->getAction('bypass_gate'));
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
    // Helper Methods
    // =========================================================================

    protected function createMockRoute(string $uses, string $middlewareGroup = 'web'): Route
    {
        $route = new Route(['GET'], '/test', ['uses' => $uses]);
        $route->middleware($middlewareGroup);

        return $route;
    }

    protected function createMockRequest(Route $route): Request
    {
        $request = Request::create('/test', 'GET');
        $request->setRouteResolver(fn () => $route);

        return $request;
    }
}
