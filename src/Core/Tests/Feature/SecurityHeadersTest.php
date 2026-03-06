<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

/**
 * Security Headers Tests (TASK-010 Phase 4)
 *
 * Verifies security headers are present on all responses.
 * Middleware: app/Http/Middleware/SecurityHeaders.php
 */
describe('Security Headers on Public Routes', function () {
    it('has X-Frame-Options header', function () {
        $response = $this->get('/');

        $response->assertOk();
        // Accept either DENY or SAMEORIGIN (both prevent cross-origin framing)
        expect($response->headers->has('X-Frame-Options'))->toBeTrue();
        expect($response->headers->get('X-Frame-Options'))->toBeIn(['DENY', 'SAMEORIGIN']);
    });

    it('has X-Content-Type-Options nosniff header', function () {
        $response = $this->get('/');

        $response->assertOk();
        expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
    });

    it('has Referrer-Policy header', function () {
        $response = $this->get('/');

        $response->assertOk();
        expect($response->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin');
    });

    it('has Content-Security-Policy header', function () {
        $response = $this->get('/');

        $response->assertOk();
        expect($response->headers->has('Content-Security-Policy'))->toBeTrue();

        $csp = $response->headers->get('Content-Security-Policy');
        expect($csp)->toContain("default-src 'self'");
    });

    it('does not expose X-Powered-By header', function () {
        $response = $this->get('/');

        $response->assertOk();
        // X-Powered-By should not be present (reveals PHP version)
        expect($response->headers->has('X-Powered-By'))->toBeFalse();
    });

    it('has X-XSS-Protection header', function () {
        $response = $this->get('/');

        $response->assertOk();
        expect($response->headers->get('X-XSS-Protection'))->toBe('1; mode=block');
    });

    it('has Permissions-Policy header', function () {
        $response = $this->get('/');

        $response->assertOk();
        expect($response->headers->has('Permissions-Policy'))->toBeTrue();
    });
});

describe('Security Headers on Authenticated Routes', function () {
    beforeEach(function () {
        $this->user = \Core\Tenant\Models\User::factory()->create();
    });

    it('has security headers on hub routes', function () {
        $response = $this->actingAs($this->user)->get('/hub');

        $response->assertOk();
        expect($response->headers->has('X-Frame-Options'))->toBeTrue();
        expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
        expect($response->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin');
        expect($response->headers->has('Content-Security-Policy'))->toBeTrue();
    });

    it('has security headers on billing routes', function () {
        $response = $this->actingAs($this->user)->get('/hub/billing');

        $response->assertOk();
        expect($response->headers->has('X-Frame-Options'))->toBeTrue();
        expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
    });
});

describe('Security Headers on API Routes', function () {
    beforeEach(function () {
        $this->user = \Core\Tenant\Models\User::factory()->create([
            'account_type' => 'hades',
        ]);
    });

    it('has security headers on API endpoints', function () {
        $response = $this->actingAs($this->user)
            ->getJson('/hub/api/dev/session');

        $response->assertOk();
        // API routes should also have security headers
        expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
    });
});

describe('Content Security Policy Details', function () {
    it('CSP restricts script sources', function () {
        $response = $this->get('/');
        $csp = $response->headers->get('Content-Security-Policy');

        expect($csp)->toContain('script-src');
    });

    it('CSP restricts frame ancestors', function () {
        $response = $this->get('/');
        $csp = $response->headers->get('Content-Security-Policy');

        // Prevents embedding in external sites
        expect($csp)->toContain("frame-ancestors 'self'");
    });

    it('CSP restricts form actions', function () {
        $response = $this->get('/');
        $csp = $response->headers->get('Content-Security-Policy');

        // Prevents form submissions to external sites
        expect($csp)->toContain("form-action 'self'");
    });

    it('CSP restricts base URI', function () {
        $response = $this->get('/');
        $csp = $response->headers->get('Content-Security-Policy');

        // Prevents base tag hijacking
        expect($csp)->toContain("base-uri 'self'");
    });
});

describe('Security Headers Consistency', function () {
    it('applies headers to multiple public routes', function () {
        $routes = ['/', '/pricing', '/about', '/login', '/services'];

        foreach ($routes as $route) {
            $response = $this->get($route);

            expect($response->headers->has('X-Frame-Options'))->toBeTrue(
                "Missing X-Frame-Options on {$route}"
            );
            expect($response->headers->has('Content-Security-Policy'))->toBeTrue(
                "Missing CSP on {$route}"
            );
        }
    });
});
