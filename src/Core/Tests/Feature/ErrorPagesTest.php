<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

/**
 * Error Pages Tests (TASK-010 Phase 3)
 *
 * Tests for static error pages in public/errors/.
 * Error handling is configured in bootstrap/app.php.
 */

use Illuminate\Support\Facades\Config;

describe('Static Error Files', function () {
    it('has 404.html error page', function () {
        $path = public_path('errors/404.html');
        expect(file_exists($path))->toBeTrue();

        $content = file_get_contents($path);
        expect($content)->toContain('Host UK');
        expect($content)->toContain('Page not found');
        expect($content)->toContain('404');
    });

    it('has 500.html error page', function () {
        $path = public_path('errors/500.html');
        expect(file_exists($path))->toBeTrue();

        $content = file_get_contents($path);
        expect($content)->toContain('Host UK');
        expect($content)->toContain('Something went wrong');
        expect($content)->toContain('HADES_PLACEHOLDER');
    });

    it('has 503.html error page', function () {
        $path = public_path('errors/503.html');
        expect(file_exists($path))->toBeTrue();

        $content = file_get_contents($path);
        expect($content)->toContain('Host UK');
    });

    it('has coming-soon.html maintenance page', function () {
        $path = public_path('errors/coming-soon.html');
        expect(file_exists($path))->toBeTrue();

        $content = file_get_contents($path);
        expect($content)->toContain('Host UK');
    });
});

describe('404 Error Page', function () {
    it('returns 404 status for non-existent routes', function () {
        $this->get('/this-route-definitely-does-not-exist-xyz123')
            ->assertNotFound();
    });

    it('renders custom 404 page in production mode', function () {
        // Temporarily disable debug mode to trigger custom error page
        Config::set('app.debug', false);

        $response = $this->get('/this-route-definitely-does-not-exist-xyz123');

        $response->assertNotFound();
        $response->assertSee('Page not found');
        $response->assertSee('Host UK');
    });

    it('includes link back to homepage on 404', function () {
        Config::set('app.debug', false);

        $response = $this->get('/non-existent-page-test');

        $response->assertNotFound();
        // Check for home link (href="/")
        $response->assertSee('href="/"', false);
    });
});

describe('403 Error Page', function () {
    it('returns 403 for forbidden access', function () {
        // Create a regular user (not hades)
        $user = \Core\Tenant\Models\User::factory()->create([
            'account_type' => 'apollo',
        ]);

        // Try to access Hades-only dev API
        $this->actingAs($user)
            ->getJson('/hub/api/dev/logs')
            ->assertForbidden();
    });
});

describe('500 Error Page', function () {
    it('has HADES placeholder for encrypted stack traces', function () {
        $path = public_path('errors/500.html');
        $content = file_get_contents($path);

        // The placeholder is used to inject encrypted error data
        expect($content)->toContain('<!-- HADES_PLACEHOLDER -->');
    });

    it('uses Host UK brand styling', function () {
        $path = public_path('errors/500.html');
        $content = file_get_contents($path);

        // Should have brand colours and styling
        expect($content)->toContain('Host UK');
        expect($content)->toContain('Something went wrong');
        // Should have a way to contact support or return home
        expect($content)->toContain('href="/"');
    });
});

describe('Error Page Brand Consistency', function () {
    it('all error pages use Host UK branding', function () {
        $errorPages = ['404.html', '500.html', '503.html', 'coming-soon.html'];

        foreach ($errorPages as $page) {
            $path = public_path("errors/{$page}");
            expect(file_exists($path))->toBeTrue();

            $content = file_get_contents($path);
            expect($content)
                ->toContain('Host UK')
                ->and($content)->toContain('host.uk.com');
        }
    });

    it('error pages do not expose stack traces', function () {
        // Verify the static files don't contain any PHP code or stack trace patterns
        $errorPages = ['404.html', '500.html', '503.html'];

        foreach ($errorPages as $page) {
            $path = public_path("errors/{$page}");
            $content = file_get_contents($path);

            expect($content)
                ->not->toContain('<?php')
                ->and($content)->not->toContain('Stack trace:')
                ->and($content)->not->toContain('vendor/laravel');
        }
    });
});

describe('HADES Encryption', function () {
    it('HadesEncrypt class exists and is functional', function () {
        expect(class_exists(\App\Support\HadesEncrypt::class))->toBeTrue();

        // Test encryption with a sample exception
        $exception = new \Exception('Test error message');
        $encrypted = \App\Support\HadesEncrypt::encrypt($exception);

        // Should return encrypted string or null if no public key
        expect($encrypted === null || is_string($encrypted))->toBeTrue();
    });
});
