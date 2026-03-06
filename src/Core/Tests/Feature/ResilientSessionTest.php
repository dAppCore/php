<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

use Core\Front\Web\Middleware\ResilientSession;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;

describe('ResilientSession Middleware', function () {
    it('passes through normal requests without issues', function () {
        $middleware = new ResilientSession;
        $request = Request::create('/test');

        $response = $middleware->handle($request, fn ($req) => response('OK'));

        expect($response->getContent())->toBe('OK');
        expect($response->getStatusCode())->toBe(200);
    });

    it('handles DecryptException by redirecting', function () {
        // This test verifies the exception handler in bootstrap/app.php
        // The middleware catches these exceptions and clears cookies
        $middleware = new ResilientSession;
        $request = Request::create('/test');

        // Simulate a request that would throw DecryptException
        $response = $middleware->handle($request, function ($req) {
            throw new DecryptException('The payload is invalid.');
        });

        // Should redirect to same URL with cookie clearing
        expect($response->getStatusCode())->toBe(302);
        expect($response->headers->get('Location'))->toContain('/test');
    });

    it('returns 419 for AJAX requests with session errors', function () {
        $middleware = new ResilientSession;
        $request = Request::create('/api/test');
        $request->headers->set('Accept', 'application/json');

        $response = $middleware->handle($request, function ($req) {
            throw new DecryptException('The payload is invalid.');
        });

        expect($response->getStatusCode())->toBe(419);
        expect($response->headers->get('Content-Type'))->toContain('application/json');
    });
});
