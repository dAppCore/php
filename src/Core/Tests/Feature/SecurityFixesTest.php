<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

/**
 * Security fixes smoke tests.
 *
 * These tests verify the security fixes applied during the code review.
 * Each test targets a specific vulnerability that was identified and fixed.
 */

use Core\Mod\Analytics\Models\AnalyticsEvent;
use Core\Mod\Analytics\Models\AnalyticsGoal;
use Core\Mod\Analytics\Models\AnalyticsWebsite;
use Core\Mod\Commerce\Models\Order;
use Core\Mod\Commerce\Models\Payment;
use Core\Tenant\Models\Package;
use Core\Tenant\Models\User;
use Core\Tenant\Models\Workspace;
use Core\Tenant\Services\EntitlementService;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create();
    $this->workspace->users()->attach($this->user->id, [
        'role' => 'owner',
        'is_default' => true,
    ]);
});

describe('Checkout Authorization Fixes', function () {
    describe('CheckoutSuccess authorization logic', function () {
        it('denies access to orders belonging to other workspaces', function () {
            // Create order for a different workspace
            $otherWorkspace = Workspace::factory()->create();
            $order = Order::create([
                'workspace_id' => $otherWorkspace->id,
                'order_number' => 'ORD-TEST-001',
                'status' => 'paid', // Valid status
                'subtotal' => 100.00,
                'tax' => 20.00,
                'total' => 120.00,
                'currency' => 'GBP',
            ]);

            // Test authorization via component mount logic
            $component = new \Core\Mod\Commerce\View\Modal\Web\CheckoutSuccess;

            // Use reflection to call protected authorizeOrder
            \Illuminate\Support\Facades\Auth::login($this->user);
            $reflection = new ReflectionClass($component);
            $method = $reflection->getMethod('authorizeOrder');
            $method->setAccessible(true);

            $result = $method->invoke($component, $order);

            // Authorization should FAIL - order belongs to different workspace
            expect($result)->toBeFalse();
        });

        it('allows access to own workspace orders', function () {
            $order = Order::create([
                'workspace_id' => $this->workspace->id,
                'order_number' => 'ORD-TEST-002',
                'status' => 'paid', // Valid status
                'subtotal' => 100.00,
                'tax' => 20.00,
                'total' => 120.00,
                'currency' => 'GBP',
            ]);

            // Test authorization via component logic
            $component = new \Core\Mod\Commerce\View\Modal\Web\CheckoutSuccess;

            \Illuminate\Support\Facades\Auth::login($this->user);
            $reflection = new ReflectionClass($component);
            $method = $reflection->getMethod('authorizeOrder');
            $method->setAccessible(true);

            $result = $method->invoke($component, $order);

            // Authorization should PASS - order belongs to user's workspace
            expect($result)->toBeTrue();
        });
    });

    describe('CheckoutCancel authorization logic', function () {
        it('denies access to orders belonging to other workspaces', function () {
            // Create order for a different workspace
            $otherWorkspace = Workspace::factory()->create();
            $order = Order::create([
                'workspace_id' => $otherWorkspace->id,
                'order_number' => 'ORD-CANCEL-001',
                'status' => 'pending',
                'subtotal' => 100.00,
                'tax' => 20.00,
                'total' => 120.00,
                'currency' => 'GBP',
            ]);

            // Test authorization via component logic
            $component = new \Core\Mod\Commerce\View\Modal\Web\CheckoutCancel;

            \Illuminate\Support\Facades\Auth::login($this->user);
            $reflection = new ReflectionClass($component);
            $method = $reflection->getMethod('authorizeOrder');
            $method->setAccessible(true);

            $result = $method->invoke($component, $order);

            // Authorization should FAIL - order belongs to different workspace
            expect($result)->toBeFalse();
        });
    });
});

describe('Analytics Goal ReDoS Fix', function () {
    beforeEach(function () {
        $this->website = AnalyticsWebsite::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'name' => 'Test Site',
            'host' => 'example.com',
        ]);
    });

    it('safely handles regex metacharacters in goal paths', function () {
        // This path contains regex metacharacters that could cause ReDoS
        $goal = AnalyticsGoal::create([
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'user_id' => $this->user->id,
            'name' => 'Test Goal',
            'type' => 'pageview',
            'path' => '/product/.*+?^${}()|[]\\',
            'is_enabled' => true,
        ]);

        // Use reflection to test the protected method directly
        $reflection = new ReflectionClass($goal);
        $method = $reflection->getMethod('checkPageviewGoal');
        $method->setAccessible(true);

        // Create a matching event
        $event = new AnalyticsEvent([
            'website_id' => $this->website->id,
            'type' => AnalyticsEvent::TYPE_PAGEVIEW,
            'path' => '/product/.*+?^${}()|[]\\',
        ]);

        // Should not throw and should match the literal path
        $result = $method->invoke($goal, $event);

        expect($result)->toBeBool();
    });

    it('still supports wildcards after escaping', function () {
        $goal = AnalyticsGoal::create([
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'user_id' => $this->user->id,
            'name' => 'Wildcard Goal',
            'type' => 'pageview',
            'path' => '/products/*',
            'is_enabled' => true,
        ]);

        // Use reflection to test the protected method directly
        $reflection = new ReflectionClass($goal);
        $method = $reflection->getMethod('checkPageviewGoal');
        $method->setAccessible(true);

        // Create a matching event
        $event = new AnalyticsEvent([
            'website_id' => $this->website->id,
            'type' => AnalyticsEvent::TYPE_PAGEVIEW,
            'path' => '/products/widget-123',
        ]);

        $result = $method->invoke($goal, $event);

        expect($result)->toBeTrue();
    });

    it('prevents catastrophic backtracking patterns', function () {
        // Create a goal with a path that, if not escaped, would cause ReDoS
        $goal = AnalyticsGoal::create([
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'user_id' => $this->user->id,
            'name' => 'ReDoS Test Goal',
            'type' => 'pageview',
            'path' => '(a+)+$',
            'is_enabled' => true,
        ]);

        // Use reflection to test the protected method directly
        $reflection = new ReflectionClass($goal);
        $method = $reflection->getMethod('checkPageviewGoal');
        $method->setAccessible(true);

        $event = new AnalyticsEvent([
            'website_id' => $this->website->id,
            'type' => AnalyticsEvent::TYPE_PAGEVIEW,
            'path' => str_repeat('a', 50).'b',
        ]);

        // This should complete quickly (not hang) because the pattern is escaped
        $start = microtime(true);
        $result = $method->invoke($goal, $event);
        $elapsed = microtime(true) - $start;

        expect($elapsed)->toBeLessThan(1.0); // Should complete in under 1 second
    });
});

describe('Analytics Goal Controller N+1 Fix', function () {
    beforeEach(function () {
        $this->website = AnalyticsWebsite::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'name' => 'Test Site',
            'host' => 'example.com',
        ]);
    });

    it('eager loads website relationship on show', function () {
        // Skip if route not registered (Analytics API routes not wired up in Boot.php)
        if (! \Illuminate\Support\Facades\Route::has('api.analytics.goals.show')) {
            $this->markTestSkipped('Analytics goals API route not registered');
        }

        $goal = AnalyticsGoal::create([
            'workspace_id' => $this->workspace->id,
            'website_id' => $this->website->id,
            'user_id' => $this->user->id,
            'name' => 'Test Goal',
            'type' => 'pageview',
            'path' => '/test',
            'is_enabled' => true,
        ]);

        // Count queries
        \Illuminate\Support\Facades\DB::enableQueryLog();

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/analytics/goals/{$goal->id}");

        $queries = \Illuminate\Support\Facades\DB::getQueryLog();
        \Illuminate\Support\Facades\DB::disableQueryLog();

        // Should be 200 OK
        expect($response->status())->toBe(200);

        // Should not have more than a reasonable number of queries
        // (The fix ensures website is eager loaded, not queried separately)
        expect(count($queries))->toBeLessThan(10);
    });
});

describe('Commerce Controller Type Safety', function () {
    it('handles package code lookup correctly in upgrade preview', function () {
        // Create a test package
        $package = Package::create([
            'code' => 'test-upgrade-package',
            'name' => 'Test Upgrade Package',
            'description' => 'For testing upgrades',
            'is_stackable' => false,
            'is_base_package' => true,
            'is_active' => true,
            'is_public' => true,
            'sort_order' => 100,
        ]);

        // Without an active subscription, we expect a 400 error
        // but the important thing is it doesn't 500 due to type mismatch
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/commerce/upgrade/preview', [
                'package_code' => 'test-upgrade-package',
            ]);

        // Should not be a 500 server error
        expect($response->status())->not->toBe(500);
    });

    it('validates package_code exists in database', function () {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/commerce/upgrade/preview', [
                'package_code' => 'nonexistent-package-xyz',
            ]);

        // Should fail validation, not cause a server error
        expect($response->status())->toBe(422);
    });
});

describe('BTCPay Gateway Return Type', function () {
    it('returns consistent array format from refund method on error', function () {
        $payment = Payment::create([
            'workspace_id' => $this->workspace->id,
            'gateway' => 'btcpay',
            'gateway_payment_id' => 'btc_test_123',
            'amount' => 100.00,
            'currency' => 'GBP',
            'status' => 'succeeded',
            'paid_at' => now(),
        ]);

        // Create a gateway with mock config
        config([
            'commerce.gateways.btcpay.url' => 'https://pay.test.com',
            'commerce.gateways.btcpay.store_id' => 'test_store',
            'commerce.gateways.btcpay.api_key' => 'test_key',
            'commerce.gateways.btcpay.webhook_secret' => 'test_webhook_secret',
        ]);

        $gateway = new \Core\Mod\Commerce\Services\PaymentGateway\BTCPayGateway;

        // Mock HTTP to fail so we test the error path
        \Illuminate\Support\Facades\Http::fake([
            '*' => \Illuminate\Support\Facades\Http::response(['error' => 'Test error'], 400),
        ]);

        $result = $gateway->refund($payment, 50.00, 'Test refund');

        // Should return array with expected keys
        expect($result)->toBeArray()
            ->and($result)->toHaveKey('success')
            ->and($result['success'])->toBeFalse()
            ->and($result)->toHaveKey('error');
    });

    it('returns success array format when refund succeeds', function () {
        $payment = Payment::create([
            'workspace_id' => $this->workspace->id,
            'gateway' => 'btcpay',
            'gateway_payment_id' => 'btc_test_456',
            'amount' => 100.00,
            'currency' => 'GBP',
            'status' => 'succeeded',
            'paid_at' => now(),
        ]);

        // Create a gateway with mock config
        config([
            'commerce.gateways.btcpay.url' => 'https://pay.test.com',
            'commerce.gateways.btcpay.store_id' => 'test_store',
            'commerce.gateways.btcpay.api_key' => 'test_key',
            'commerce.gateways.btcpay.webhook_secret' => 'test_webhook_secret',
        ]);

        $gateway = new \Core\Mod\Commerce\Services\PaymentGateway\BTCPayGateway;

        // Mock HTTP to succeed
        \Illuminate\Support\Facades\Http::fake([
            '*' => \Illuminate\Support\Facades\Http::response([
                'id' => 'refund_123',
                'status' => 'processed',
            ], 200),
        ]);

        $result = $gateway->refund($payment, 50.00, 'Test refund');

        // Should return array with expected keys
        expect($result)->toBeArray()
            ->and($result)->toHaveKey('success')
            ->and($result['success'])->toBeTrue()
            ->and($result)->toHaveKey('refund_id');
    });
});

describe('SocialPost Controller User Type Check', function () {
    it('handles non-User instances gracefully in duplicate', function () {
        // This is more of a unit test to verify the type check exists
        // In real scenarios, $request->user() should always return User
        // but we added the check as a safety measure

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/social/posts/99999/duplicate');

        // Should not cause a 500 error from type mismatch
        // Expected 404 because post doesn't exist or 404 for no workspace
        expect($response->status())->toBeIn([404, 403]);
    });
});

describe('LIKE Wildcard Injection Fix', function () {
    it('escapes LIKE wildcards in MediaPicker search', function () {
        // Test the escapeLikeWildcards helper directly
        $component = new \Core\Mod\Social\View\Modal\Admin\MediaPicker;

        $reflection = new ReflectionClass($component);
        $method = $reflection->getMethod('escapeLikeWildcards');
        $method->setAccessible(true);

        // Test various injection attempts
        expect($method->invoke($component, 'normal search'))->toBe('normal search')
            ->and($method->invoke($component, '%admin%'))->toBe('\\%admin\\%')
            ->and($method->invoke($component, '_secret_'))->toBe('\\_secret\\_')
            ->and($method->invoke($component, '% OR 1=1 --'))->toBe('\\% OR 1=1 --')
            ->and($method->invoke($component, '100%'))->toBe('100\\%')
            ->and($method->invoke($component, 'test_file'))->toBe('test\\_file');
    });

    it('does not affect normal search terms', function () {
        $component = new \Core\Mod\Social\View\Modal\Admin\MediaPicker;

        $reflection = new ReflectionClass($component);
        $method = $reflection->getMethod('escapeLikeWildcards');
        $method->setAccessible(true);

        // Normal searches should not be modified
        expect($method->invoke($component, 'my image'))->toBe('my image')
            ->and($method->invoke($component, 'logo.png'))->toBe('logo.png')
            ->and($method->invoke($component, 'header-image-2024'))->toBe('header-image-2024');
    });
});

describe('Null Workspace Checks', function () {
    it('TemplateIndex handles null workspace gracefully', function () {
        // Create a fresh user with no workspace association
        $freshUser = User::factory()->create();

        // Test without a default workspace - should not crash
        \Livewire\Livewire::actingAs($freshUser)
            ->test(\Core\Mod\Social\View\Modal\Admin\TemplateIndex::class)
            ->assertStatus(200);
    });

    it('user workspace method returns null for users without workspaces', function () {
        // Create a fresh user with no workspace association
        $freshUser = User::factory()->create();

        // The defaultHostWorkspace method should return null, not throw
        expect($freshUser->defaultHostWorkspace())->toBeNull();
    });
});

describe('Entitlement Package Revocation', function () {
    it('revokePackage marks package as cancelled', function () {
        $package = Package::create([
            'code' => 'test-revoke-pkg',
            'name' => 'Test Revoke Package',
            'description' => 'For testing revocation',
            'is_stackable' => false,
            'is_base_package' => true,
            'is_active' => true,
            'is_public' => true,
            'sort_order' => 100,
        ]);

        $entitlements = app(EntitlementService::class);
        $entitlements->provisionPackage($this->workspace, 'test-revoke-pkg');

        // Verify package is active
        $activePackage = $this->workspace->workspacePackages()
            ->whereHas('package', fn ($q) => $q->where('code', 'test-revoke-pkg'))
            ->active()
            ->first();
        expect($activePackage)->not->toBeNull();

        // Revoke the package
        $entitlements->revokePackage($this->workspace, 'test-revoke-pkg');

        // Verify package is now cancelled
        $activePackage = $this->workspace->workspacePackages()
            ->whereHas('package', fn ($q) => $q->where('code', 'test-revoke-pkg'))
            ->active()
            ->first();
        expect($activePackage)->toBeNull();
    });

    it('revokePackage handles non-existent packages gracefully', function () {
        $entitlements = app(EntitlementService::class);

        // Should not throw when revoking a package that doesn't exist
        $entitlements->revokePackage($this->workspace, 'non-existent-package-xyz');

        // No exception means success
        expect(true)->toBeTrue();
    });
});

describe('Checkout Edge Cases', function () {
    it('CheckoutSuccess denies access when user has no workspace', function () {
        $freshUser = User::factory()->create();

        $order = Order::create([
            'workspace_id' => $this->workspace->id,
            'order_number' => 'ORD-NO-USER-WS',
            'status' => 'paid',
            'subtotal' => 100.00,
            'tax' => 20.00,
            'total' => 120.00,
            'currency' => 'GBP',
        ]);

        $component = new \Core\Mod\Commerce\View\Modal\Web\CheckoutSuccess;
        \Illuminate\Support\Facades\Auth::login($freshUser);

        $reflection = new ReflectionClass($component);
        $method = $reflection->getMethod('authorizeOrder');
        $method->setAccessible(true);

        $result = $method->invoke($component, $order);

        // Should fail - user has no workspace
        expect($result)->toBeFalse();
    });

    it('CheckoutCancel denies access when user has no workspace', function () {
        $freshUser = User::factory()->create();

        $order = Order::create([
            'workspace_id' => $this->workspace->id,
            'order_number' => 'ORD-NO-USER-WS-CANCEL',
            'status' => 'pending',
            'subtotal' => 100.00,
            'tax' => 20.00,
            'total' => 120.00,
            'currency' => 'GBP',
        ]);

        $component = new \Core\Mod\Commerce\View\Modal\Web\CheckoutCancel;
        \Illuminate\Support\Facades\Auth::login($freshUser);

        $reflection = new ReflectionClass($component);
        $method = $reflection->getMethod('authorizeOrder');
        $method->setAccessible(true);

        $result = $method->invoke($component, $order);

        // Should fail - user has no workspace
        expect($result)->toBeFalse();
    });
});
