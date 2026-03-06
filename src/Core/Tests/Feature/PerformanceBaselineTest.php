<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

/**
 * Performance Baseline Tests (TASK-010 Phase 5)
 *
 * Measures response times and query counts for key routes.
 * These tests establish baseline performance metrics for production.
 *
 * Target Response Times:
 * - Homepage: <200ms (acceptable: <400ms)
 * - Pricing: <300ms (acceptable: <500ms)
 * - Hub: <500ms (acceptable: <800ms)
 * - Social: <600ms (acceptable: <1000ms)
 */

use Core\Tenant\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    // Enable query logging for all tests
    DB::enableQueryLog();
});

afterEach(function () {
    DB::disableQueryLog();
});

describe('Public Route Response Times', function () {
    it('homepage responds within acceptable time', function () {
        DB::flushQueryLog();

        $start = microtime(true);
        $response = $this->get('/');
        $duration = (microtime(true) - $start) * 1000; // Convert to ms

        $response->assertOk();

        $queries = DB::getQueryLog();
        $queryCount = count($queries);

        // Log performance metrics
        $this->addToAssertionCount(1);

        // Acceptable threshold - relaxed for development/CI (cold start overhead)
        // Target: <200ms in production, <600ms acceptable in dev/CI
        $threshold = app()->environment('production') ? 400 : 600;
        expect($duration)->toBeLessThan($threshold, "Homepage took {$duration}ms (target: <200ms, acceptable: <{$threshold}ms)");

        // Document metrics in test output
        fwrite(STDERR, sprintf(
            "\n  [PERF] Homepage: %.2fms, %d queries\n",
            $duration,
            $queryCount
        ));
    });

    it('pricing page responds within acceptable time', function () {
        DB::flushQueryLog();

        $start = microtime(true);
        $response = $this->get('/pricing');
        $duration = (microtime(true) - $start) * 1000;

        $response->assertOk();

        $queries = DB::getQueryLog();
        $queryCount = count($queries);

        expect($duration)->toBeLessThan(500, "Pricing took {$duration}ms (target: <300ms, acceptable: <500ms)");

        fwrite(STDERR, sprintf(
            "\n  [PERF] Pricing: %.2fms, %d queries\n",
            $duration,
            $queryCount
        ));
    });

    it('services page responds within acceptable time', function () {
        DB::flushQueryLog();

        $start = microtime(true);
        $response = $this->get('/services');
        $duration = (microtime(true) - $start) * 1000;

        $response->assertOk();

        $queries = DB::getQueryLog();
        $queryCount = count($queries);

        expect($duration)->toBeLessThan(400, "Services took {$duration}ms");

        fwrite(STDERR, sprintf(
            "\n  [PERF] Services: %.2fms, %d queries\n",
            $duration,
            $queryCount
        ));
    });

    it('about page responds within acceptable time', function () {
        DB::flushQueryLog();

        $start = microtime(true);
        $response = $this->get('/about');
        $duration = (microtime(true) - $start) * 1000;

        $response->assertOk();

        $queries = DB::getQueryLog();
        $queryCount = count($queries);

        expect($duration)->toBeLessThan(400, "About took {$duration}ms");

        fwrite(STDERR, sprintf(
            "\n  [PERF] About: %.2fms, %d queries\n",
            $duration,
            $queryCount
        ));
    });

    it('login page responds within acceptable time', function () {
        DB::flushQueryLog();

        $start = microtime(true);
        $response = $this->get('/login');
        $duration = (microtime(true) - $start) * 1000;

        $response->assertOk();

        $queries = DB::getQueryLog();
        $queryCount = count($queries);

        expect($duration)->toBeLessThan(300, "Login took {$duration}ms");

        fwrite(STDERR, sprintf(
            "\n  [PERF] Login: %.2fms, %d queries\n",
            $duration,
            $queryCount
        ));
    });
});

describe('Authenticated Route Response Times', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
    });

    it('hub dashboard responds within acceptable time', function () {
        DB::flushQueryLog();

        $start = microtime(true);
        $response = $this->actingAs($this->user)->get('/hub');
        $duration = (microtime(true) - $start) * 1000;

        $response->assertOk();

        $queries = DB::getQueryLog();
        $queryCount = count($queries);

        expect($duration)->toBeLessThan(800, "Hub took {$duration}ms (target: <500ms, acceptable: <800ms)");

        fwrite(STDERR, sprintf(
            "\n  [PERF] Hub Dashboard: %.2fms, %d queries\n",
            $duration,
            $queryCount
        ));
    });

    it('social dashboard responds within acceptable time', function () {
        DB::flushQueryLog();

        $start = microtime(true);
        $response = $this->actingAs($this->user)->get('/hub/social');
        $duration = (microtime(true) - $start) * 1000;

        $response->assertOk();

        $queries = DB::getQueryLog();
        $queryCount = count($queries);

        expect($duration)->toBeLessThan(1000, "Social took {$duration}ms (target: <600ms, acceptable: <1000ms)");

        fwrite(STDERR, sprintf(
            "\n  [PERF] Social Dashboard: %.2fms, %d queries\n",
            $duration,
            $queryCount
        ));
    });

    it('billing page responds within acceptable time', function () {
        DB::flushQueryLog();

        $start = microtime(true);
        $response = $this->actingAs($this->user)->get('/hub/billing');
        $duration = (microtime(true) - $start) * 1000;

        $response->assertOk();

        $queries = DB::getQueryLog();
        $queryCount = count($queries);

        expect($duration)->toBeLessThan(800, "Billing took {$duration}ms");

        fwrite(STDERR, sprintf(
            "\n  [PERF] Billing: %.2fms, %d queries\n",
            $duration,
            $queryCount
        ));
    });

    it('profile page responds within acceptable time', function () {
        DB::flushQueryLog();

        $start = microtime(true);
        $response = $this->actingAs($this->user)->get('/hub/profile');
        $duration = (microtime(true) - $start) * 1000;

        $response->assertOk();

        $queries = DB::getQueryLog();
        $queryCount = count($queries);

        expect($duration)->toBeLessThan(600, "Profile took {$duration}ms");

        fwrite(STDERR, sprintf(
            "\n  [PERF] Profile: %.2fms, %d queries\n",
            $duration,
            $queryCount
        ));
    });
});

describe('Query Count Baselines', function () {
    it('homepage has reasonable query count', function () {
        DB::flushQueryLog();

        $this->get('/')->assertOk();

        $queries = DB::getQueryLog();
        $queryCount = count($queries);

        // Homepage should be relatively query-light
        expect($queryCount)->toBeLessThan(20, "Homepage has {$queryCount} queries (expected <20)");

        fwrite(STDERR, sprintf("\n  [QUERIES] Homepage: %d queries\n", $queryCount));
    });

    it('pricing page has reasonable query count', function () {
        DB::flushQueryLog();

        $this->get('/pricing')->assertOk();

        $queries = DB::getQueryLog();
        $queryCount = count($queries);

        // Pricing might load packages/features
        expect($queryCount)->toBeLessThan(30, "Pricing has {$queryCount} queries (expected <30)");

        fwrite(STDERR, sprintf("\n  [QUERIES] Pricing: %d queries\n", $queryCount));
    });

    it('hub dashboard has reasonable query count', function () {
        $user = User::factory()->create();
        DB::flushQueryLog();

        $this->actingAs($user)->get('/hub')->assertOk();

        $queries = DB::getQueryLog();
        $queryCount = count($queries);

        // Dashboard loads multiple widgets
        expect($queryCount)->toBeLessThan(50, "Hub has {$queryCount} queries (expected <50)");

        fwrite(STDERR, sprintf("\n  [QUERIES] Hub Dashboard: %d queries\n", $queryCount));
    });
});

describe('N+1 Query Detection', function () {
    it('pricing page does not have obvious N+1 patterns', function () {
        DB::flushQueryLog();

        $this->get('/pricing')->assertOk();

        $queries = DB::getQueryLog();

        // Look for repeated similar queries (potential N+1)
        $queryPatterns = [];
        foreach ($queries as $query) {
            // Normalize query by removing specific IDs
            $pattern = preg_replace('/= \d+/', '= ?', $query['query']);
            $pattern = preg_replace('/in \([^)]+\)/', 'in (?)', $pattern);
            $queryPatterns[$pattern] = ($queryPatterns[$pattern] ?? 0) + 1;
        }

        // Flag if any query pattern repeats more than 5 times
        $suspiciousPatterns = array_filter($queryPatterns, fn ($count) => $count > 5);

        if (! empty($suspiciousPatterns)) {
            fwrite(STDERR, "\n  [WARNING] Potential N+1 queries detected:\n");
            foreach ($suspiciousPatterns as $pattern => $count) {
                fwrite(STDERR, "    - {$count}x: ".substr($pattern, 0, 80)."...\n");
            }
        }

        // This is a soft check - we log but don't fail
        expect(count($suspiciousPatterns))->toBeLessThan(3, 'Too many potential N+1 query patterns');
    });

    it('services page does not have obvious N+1 patterns', function () {
        DB::flushQueryLog();

        $this->get('/services')->assertOk();

        $queries = DB::getQueryLog();

        $queryPatterns = [];
        foreach ($queries as $query) {
            $pattern = preg_replace('/= \d+/', '= ?', $query['query']);
            $pattern = preg_replace('/in \([^)]+\)/', 'in (?)', $pattern);
            $queryPatterns[$pattern] = ($queryPatterns[$pattern] ?? 0) + 1;
        }

        $suspiciousPatterns = array_filter($queryPatterns, fn ($count) => $count > 5);

        expect(count($suspiciousPatterns))->toBeLessThan(3, 'Too many potential N+1 query patterns');
    });
});

describe('Performance Summary', function () {
    it('generates performance baseline report', function () {
        $routes = [
            '/' => ['target' => 200, 'acceptable' => 400],
            '/pricing' => ['target' => 300, 'acceptable' => 500],
            '/services' => ['target' => 200, 'acceptable' => 400],
            '/about' => ['target' => 200, 'acceptable' => 400],
            '/login' => ['target' => 150, 'acceptable' => 300],
        ];

        fwrite(STDERR, "\n\n  ╔══════════════════════════════════════════════════════════════╗\n");
        fwrite(STDERR, "  ║           PERFORMANCE BASELINE REPORT                       ║\n");
        fwrite(STDERR, "  ╠══════════════════════════════════════════════════════════════╣\n");
        fwrite(STDERR, sprintf("  ║ %-20s │ %8s │ %8s │ %8s ║\n", 'Route', 'Time', 'Queries', 'Status'));
        fwrite(STDERR, "  ╟──────────────────────┼──────────┼──────────┼──────────╢\n");

        $allPassed = true;

        foreach ($routes as $route => $thresholds) {
            DB::flushQueryLog();

            $start = microtime(true);
            $response = $this->get($route);
            $duration = (microtime(true) - $start) * 1000;

            $queries = count(DB::getQueryLog());
            $status = $duration <= $thresholds['acceptable'] ? '✓ PASS' : '✗ SLOW';

            if ($duration > $thresholds['acceptable']) {
                $allPassed = false;
            }

            fwrite(STDERR, sprintf(
                "  ║ %-20s │ %6.0fms │ %8d │ %8s ║\n",
                $route,
                $duration,
                $queries,
                $status
            ));
        }

        fwrite(STDERR, "  ╚══════════════════════════════════════════════════════════════╝\n\n");

        expect($allPassed)->toBeTrue('Some routes exceeded acceptable response times');
    });
});
