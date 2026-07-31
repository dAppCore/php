<?php

declare(strict_types=1);

namespace Core\Tests\Feature;

use Core\Front\Boot;
use Core\Tests\TestCase;
use Illuminate\Foundation\Configuration\Middleware;

/**
 * Regression coverage: Core\Front\Boot::$providers listed Client\Boot::class
 * and Core\Front\Boot::middleware() called Client\Boot::middleware(), but
 * src/Core/Front/Client/ was deleted when the Client frontage was extracted
 * to the standalone php-client package (commit affedb3, "refactor: extract
 * Service + Client to standalone packages") — the two references in this
 * class were never updated. Any real application booting through
 * Application::configure(withMiddleware: Core\Front\Boot::middleware(...))
 * — the documented entry point — fataled with "Class Core\Front\Client\Boot
 * not found" before a single request could be served, regardless of
 * whether php-client was installed.
 */
class FrontBootMiddlewareTest extends TestCase
{
    public function test_front_boot_middleware_does_not_reference_the_deleted_client_frontage(): void
    {
        $middleware = new Middleware();

        // Must not throw \Error: Class "Core\Front\Client\Boot" not found.
        Boot::middleware($middleware);

        $this->assertTrue(true);
    }

    public function test_front_boot_aggregate_providers_do_not_reference_the_deleted_client_frontage(): void
    {
        $ref = new \ReflectionClass(Boot::class);
        $providers = $ref->getDefaultProperties()['providers'];

        $this->assertNotContains('Core\Front\Client\Boot', $providers);
    }
}
