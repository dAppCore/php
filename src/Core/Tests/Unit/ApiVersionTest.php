<?php

declare(strict_types=1);

namespace Core\Tests\Unit;

use Core\Front\Api\ApiVersionService;
use Core\Front\Api\Middleware\ApiSunset;
use Core\Front\Api\Middleware\ApiVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\TestCase;

/**
 * Tests for API Versioning components.
 *
 * @covers \Core\Front\Api\Middleware\ApiVersion
 * @covers \Core\Front\Api\Middleware\ApiSunset
 * @covers \Core\Front\Api\ApiVersionService
 */
class ApiVersionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Mock config helper
        if (! function_exists('config')) {
            // This will be overridden when running in Laravel context
        }
    }

    /**
     * Helper to create middleware with mocked config.
     */
    protected function createMiddleware(array $config = []): ApiVersion
    {
        $defaults = [
            'default' => 1,
            'current' => 2,
            'supported' => [1, 2],
            'deprecated' => [1],
            'sunset' => [1 => '2025-12-31'],
        ];

        $merged = array_merge($defaults, $config);

        // We need to run these tests in Laravel context
        // This file documents the expected behavior
        return new ApiVersion;
    }
}

/*
 * API Version Test Documentation
 *
 * These tests require a Laravel application context to run.
 * Run them using: php artisan test --filter=ApiVersion
 *
 * Expected behaviors to test:
 *
 * ApiVersion Middleware:
 * - extracts version from URL path /api/v1/
 * - extracts version from URL path /v2/ without api prefix
 * - extracts version from Accept-Version header
 * - extracts version from Accept-Version header without v prefix
 * - extracts version from Accept header vendor type
 * - uses default version when none specified
 * - prefers URL path over headers
 * - returns 400 for unsupported version
 * - returns 400 when version is below minimum required
 * - adds X-API-Version header to response
 * - adds deprecation headers for deprecated versions
 * - does not add deprecation headers for current version
 *
 * ApiSunset Middleware:
 * - adds Sunset header with date
 * - adds Link header when replacement provided
 * - formats date to RFC7231 format
 *
 * ApiVersionService:
 * - returns current version from request
 * - returns version string from request
 * - checks specific version
 * - provides version shortcuts (isV1, isV2)
 * - checks minimum version
 * - checks if version is deprecated
 * - returns configuration values
 * - checks if version is supported
 * - negotiates response based on version
 * - falls back to lower version in negotiation
 * - transforms data based on version
 */
