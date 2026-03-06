<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

/**
 * Admin Route Smoke Tests
 *
 * Discovery-based tests that automatically find and verify all admin routes.
 * No manual route lists to maintain - new routes are tested automatically.
 */

use Core\Tenant\Enums\UserTier;
use Core\Tenant\Models\User;
use Core\Tenant\Models\Workspace;
use Illuminate\Support\Facades\Route;

uses()->group('admin-smoke');

beforeEach(function () {
    $this->hadesUser = User::factory()->create(['tier' => UserTier::HADES]);
    $this->workspace = Workspace::factory()->create();
    $this->hadesUser->hostWorkspaces()->attach($this->workspace->id, ['is_default' => true]);
});

describe('Admin Route Discovery', function () {
    it('discovers all admin GET routes', function () {
        $routes = discoverAdminRoutes();

        expect($routes)->not->toBeEmpty('No admin routes found - is the app bootstrapped?');

        // Log discovered routes for visibility
        dump('Discovered '.count($routes).' admin routes');
    });

    it('all admin routes respond without server errors', function () {
        $routes = discoverAdminRoutes();
        $failures = [];

        foreach ($routes as $route) {
            $uri = $route->uri();

            // Skip routes with required parameters for now
            if (preg_match('/\{[^}]+\}/', $uri)) {
                continue;
            }

            try {
                $response = $this->actingAs($this->hadesUser)->get('/'.$uri);
            } catch (\Throwable $e) {
                $failures[] = [
                    'uri' => $uri,
                    'status' => 500,
                    'error' => get_class($e).': '.$e->getMessage(),
                ];

                continue;
            }

            // 200 = OK, 403 = permission denied (acceptable), 302 = redirect (acceptable)
            // 500 = server error (FAIL), 404 = route broken (FAIL for admin routes)
            if ($response->status() >= 500) {
                // Try to extract error from response
                $content = $response->getContent();
                $errorMsg = 'Server error';
                if (preg_match('/Exception:?\s*([^\n<]+)/i', $content, $m)) {
                    $errorMsg = trim($m[1]);
                } elseif (preg_match('/<title>([^<]+)<\/title>/i', $content, $m)) {
                    $errorMsg = trim($m[1]);
                }
                $failures[] = [
                    'uri' => $uri,
                    'status' => $response->status(),
                    'error' => $errorMsg,
                ];
            } elseif ($response->status() === 404) {
                $failures[] = [
                    'uri' => $uri,
                    'status' => $response->status(),
                    'error' => 'Route not found',
                ];
            }
        }

        if (! empty($failures)) {
            $message = "Admin routes with errors:\n";
            foreach ($failures as $f) {
                $message .= "  - {$f['uri']}: {$f['status']} ({$f['error']})\n";
            }
            $this->fail($message);
        }

        expect($failures)->toBeEmpty();
    });

    it('no admin routes return 500 errors', function () {
        $routes = discoverAdminRoutes();

        foreach ($routes as $route) {
            $uri = $route->uri();

            // Skip parameterised routes
            if (preg_match('/\{[^}]+\}/', $uri)) {
                continue;
            }

            $response = $this->actingAs($this->hadesUser)->get('/'.$uri);

            expect($response->status())
                ->not->toBe(500, "Route /{$uri} returned 500 error");
        }
    });
});

describe('Admin Route Security', function () {
    it('all admin routes require authentication', function () {
        $routes = discoverAdminRoutes();

        foreach ($routes as $route) {
            $uri = $route->uri();

            // Skip parameterised routes
            if (preg_match('/\{[^}]+\}/', $uri)) {
                continue;
            }

            $response = $this->get('/'.$uri);

            // Should redirect to login (302) or return 401/403
            expect($response->status())
                ->toBeIn([302, 401, 403], "Route /{$uri} is accessible without auth");
        }
    });

    it('admin-only routes deny non-Hades users', function () {
        $regularUser = User::factory()->create(['tier' => UserTier::FREE]);
        $regularUser->hostWorkspaces()->attach($this->workspace->id, ['is_default' => true]);

        $routes = discoverAdminRoutes();

        foreach ($routes as $route) {
            $uri = $route->uri();

            // Skip parameterised routes
            if (preg_match('/\{[^}]+\}/', $uri)) {
                continue;
            }

            // Only check routes that are truly admin-only (hub/admin/*)
            // Regular hub/* routes are for all authenticated users
            if (! str_starts_with($uri, 'hub/admin/')) {
                continue;
            }

            $response = $this->actingAs($regularUser)->get('/'.$uri);

            // Should be 403 Forbidden for admin-only routes
            expect($response->status())
                ->toBe(403, "Route /{$uri} is accessible by non-Hades user");
        }
    });
});

describe('Admin Route Coverage', function () {
    it('reports admin route statistics', function () {
        $routes = discoverAdminRoutes();

        $total = count($routes);
        $withParams = 0;
        $withoutParams = 0;

        foreach ($routes as $route) {
            if (preg_match('/\{[^}]+\}/', $route->uri())) {
                $withParams++;
            } else {
                $withoutParams++;
            }
        }

        dump([
            'total_admin_routes' => $total,
            'testable_routes' => $withoutParams,
            'parameterised_routes' => $withParams,
        ]);

        expect($total)->toBeGreaterThan(0);
    });
});

describe('Hub Route Architecture', function () {
    // Note: 'admin' middleware group = routes served from admin DOMAIN (for all authenticated users)
    // This is separate from hub/admin/* routes which require Hades TIER

    it('all hub routes use admin domain routing', function () {
        // All hub/* routes should use either 'admin' middleware group
        // OR the equivalent ['web', 'admin.domain', 'auth'] stack
        // This ensures they're served from the admin domain with proper session handling
        $hubRoutes = discoverHubRoutes();
        $missing = [];

        foreach ($hubRoutes as $route) {
            $uri = $route->uri();
            $middleware = $route->middleware();

            // Check for either 'admin' group or equivalent explicit stack
            $hasAdminGroup = in_array('admin', $middleware);
            $hasExplicitStack = in_array('admin.domain', $middleware) && in_array('auth', $middleware);

            if (! $hasAdminGroup && ! $hasExplicitStack) {
                $missing[] = $uri.' ('.implode(', ', $middleware).')';
            }
        }

        if (! empty($missing)) {
            $message = "Hub routes missing admin domain routing:\n";
            foreach ($missing as $uri) {
                $message .= "  - {$uri}\n";
            }
            $this->fail($message);
        }

        expect($missing)->toBeEmpty();
    });

    it('regular hub routes are accessible to all authenticated users', function () {
        // hub/* routes (excluding hub/admin/*) should work for any authenticated user
        // Exception: Some routes have component-level Hades checks (technical debt)
        $regularUser = User::factory()->create(['tier' => UserTier::FREE]);
        $regularUser->hostWorkspaces()->attach($this->workspace->id, ['is_default' => true]);

        // Routes with component-level Hades checks (should be moved to hub/admin/*)
        // TODO: These routes should be refactored to hub/admin/* prefix
        // Note: Pattern = prefix match (hub/dev matches hub/dev/logs, hub/dev/routes, etc.)
        $knownHadesPrefixes = [
            'hub/databases',
            'hub/console',
            'hub/platform',
            'hub/ai-services',
            'hub/prompts',
            'hub/entitlements/',
            'hub/deployments',
            'hub/bio/shortlinks',
            'hub/bio/themes',
            'hub/bio/templates',
            'hub/bio/analytics',
            'hub/dev/',
            'hub/api/dev/',
            'hub/agents',
            'hub/commerce',
            'hub/honeypot',
        ];

        // Convert prefix matches to full route check
        $isKnownHadesRoute = function (string $uri) use ($knownHadesPrefixes): bool {
            foreach ($knownHadesPrefixes as $prefix) {
                if ($uri === $prefix || str_starts_with($uri, $prefix)) {
                    return true;
                }
            }

            return false;
        };

        $hubRoutes = discoverHubRoutes();
        $unexpectedDenied = [];

        foreach ($hubRoutes as $route) {
            $uri = $route->uri();

            // Skip parameterised routes
            if (preg_match('/\{[^}]+\}/', $uri)) {
                continue;
            }

            // Skip hub/admin/* routes - those require Hades tier (tested separately)
            if (str_starts_with($uri, 'hub/admin/')) {
                continue;
            }

            // Skip known Hades routes (component-level checks)
            if ($isKnownHadesRoute($uri)) {
                continue;
            }

            $response = $this->actingAs($regularUser)->get('/'.$uri);

            // Should NOT be 403 for regular authenticated users
            if ($response->status() === 403) {
                $unexpectedDenied[] = $uri;
            }
        }

        if (! empty($unexpectedDenied)) {
            $message = "Routes denying regular users (add to knownHadesRoutes or move to hub/admin/*):\n";
            foreach ($unexpectedDenied as $uri) {
                $message .= "  - {$uri}\n";
            }
            $this->fail($message);
        }

        expect($unexpectedDenied)->toBeEmpty();
    });

    it('reports hub route statistics', function () {
        $hubRoutes = discoverHubRoutes();

        $adminOnly = 0;
        $userAccessible = 0;

        foreach ($hubRoutes as $route) {
            $uri = $route->uri();
            if (str_starts_with($uri, 'hub/admin/')) {
                $adminOnly++;
            } else {
                $userAccessible++;
            }
        }

        dump([
            'total_hub_routes' => count($hubRoutes),
            'hades_only_routes' => $adminOnly,
            'user_accessible_routes' => $userAccessible,
        ]);

        expect(count($hubRoutes))->toBeGreaterThan(0);
    });
});

/**
 * Discover all admin GET routes from Laravel's route registrar.
 *
 * Uses middleware detection to find admin routes, not URL patterns.
 * This catches all routes with 'admin' middleware regardless of URL structure.
 *
 * @return array<\Illuminate\Routing\Route>
 */
function discoverAdminRoutes(): array
{
    $allRoutes = Route::getRoutes()->getRoutes();

    return collect($allRoutes)
        ->filter(function ($route) {
            // Only GET routes
            if (! in_array('GET', $route->methods())) {
                return false;
            }

            // Check if route has 'admin' middleware (by name or in group)
            $middleware = $route->middleware();

            // 'admin' is the middleware group for admin routes
            if (in_array('admin', $middleware)) {
                return true;
            }

            // Also catch routes in the admin middleware group via action
            $action = $route->getAction();
            if (isset($action['middleware'])) {
                $actionMiddleware = is_array($action['middleware']) ? $action['middleware'] : [$action['middleware']];
                if (in_array('admin', $actionMiddleware)) {
                    return true;
                }
            }

            // Fallback: URL pattern matching for routes that may not have middleware set
            $uri = $route->uri();
            if (str_starts_with($uri, 'hub/admin/') || str_starts_with($uri, 'admin/')) {
                return true;
            }

            return false;
        })
        ->values()
        ->all();
}

/**
 * Discover all hub GET routes (hub/*) from Laravel's route registrar.
 *
 * @return array<\Illuminate\Routing\Route>
 */
function discoverHubRoutes(): array
{
    $allRoutes = Route::getRoutes()->getRoutes();

    return collect($allRoutes)
        ->filter(function ($route) {
            // Only GET routes
            if (! in_array('GET', $route->methods())) {
                return false;
            }

            // Only hub/* routes
            $uri = $route->uri();

            return str_starts_with($uri, 'hub/');
        })
        ->values()
        ->all();
}
