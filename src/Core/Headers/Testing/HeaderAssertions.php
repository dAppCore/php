<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Headers\Testing;

use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Assert;

/**
 * Testing utilities for HTTP security headers.
 *
 * Provides assertion methods and helpers for testing security headers
 * in your application's HTTP responses.
 *
 * ## Usage in Test Classes
 *
 * ```php
 * use Core\Headers\Testing\HeaderAssertions;
 *
 * class MySecurityTest extends TestCase
 * {
 *     use HeaderAssertions;
 *
 *     public function test_security_headers_are_present(): void
 *     {
 *         $response = $this->get('/');
 *
 *         $this->assertHasSecurityHeaders($response);
 *         $this->assertHasHstsHeader($response);
 *         $this->assertHasCspHeader($response);
 *     }
 * }
 * ```
 *
 * ## Available Assertions
 *
 * | Method | Description |
 * |--------|-------------|
 * | `assertHasSecurityHeaders()` | Assert all standard security headers present |
 * | `assertHasHstsHeader()` | Assert HSTS header present with valid config |
 * | `assertHasCspHeader()` | Assert CSP header present |
 * | `assertCspContainsDirective()` | Assert CSP contains a specific directive |
 * | `assertCspContainsSource()` | Assert CSP directive contains a source |
 * | `assertCspDoesNotContainSource()` | Assert CSP directive does not contain source |
 * | `assertHasPermissionsPolicy()` | Assert Permissions-Policy header present |
 * | `assertPermissionsPolicyFeature()` | Assert specific feature in Permissions-Policy |
 * | `assertHasXFrameOptions()` | Assert X-Frame-Options header present |
 * | `assertHasXContentTypeOptions()` | Assert X-Content-Type-Options header present |
 * | `assertHasReferrerPolicy()` | Assert Referrer-Policy header present |
 * | `assertHasCspNonce()` | Assert CSP contains a nonce directive |
 * | `assertNoCspUnsafeInline()` | Assert CSP does not use unsafe-inline |
 */
trait HeaderAssertions
{
    /**
     * Assert that all standard security headers are present.
     *
     * Checks for:
     * - X-Content-Type-Options: nosniff
     * - X-Frame-Options
     * - X-XSS-Protection
     * - Referrer-Policy
     *
     * @param  TestResponse  $response  The HTTP response to check
     * @return $this
     */
    public function assertHasSecurityHeaders(TestResponse $response): self
    {
        $response->assertHeader('X-Content-Type-Options');
        $response->assertHeader('X-Frame-Options');
        $response->assertHeader('X-XSS-Protection');
        $response->assertHeader('Referrer-Policy');

        return $this;
    }

    /**
     * Assert that HSTS header is present and properly configured.
     *
     * @param  TestResponse  $response  The HTTP response to check
     * @param  int|null  $minMaxAge  Minimum max-age value (optional)
     * @param  bool|null  $includeSubdomains  Whether includeSubDomains should be present (optional)
     * @param  bool|null  $preload  Whether preload should be present (optional)
     * @return $this
     */
    public function assertHasHstsHeader(
        TestResponse $response,
        ?int $minMaxAge = null,
        ?bool $includeSubdomains = null,
        ?bool $preload = null
    ): self {
        $response->assertHeader('Strict-Transport-Security');

        $hsts = $response->headers->get('Strict-Transport-Security');
        Assert::assertNotNull($hsts, 'HSTS header should not be null');

        // Check max-age
        if ($minMaxAge !== null) {
            preg_match('/max-age=(\d+)/', $hsts, $matches);
            Assert::assertNotEmpty($matches, 'HSTS should contain max-age directive');
            Assert::assertGreaterThanOrEqual($minMaxAge, (int) $matches[1], "HSTS max-age should be at least {$minMaxAge}");
        }

        // Check includeSubDomains
        if ($includeSubdomains === true) {
            Assert::assertStringContainsString('includeSubDomains', $hsts, 'HSTS should include subdomains');
        } elseif ($includeSubdomains === false) {
            Assert::assertStringNotContainsString('includeSubDomains', $hsts, 'HSTS should not include subdomains');
        }

        // Check preload
        if ($preload === true) {
            Assert::assertStringContainsString('preload', $hsts, 'HSTS should have preload flag');
        } elseif ($preload === false) {
            Assert::assertStringNotContainsString('preload', $hsts, 'HSTS should not have preload flag');
        }

        return $this;
    }

    /**
     * Assert that CSP header is present.
     *
     * @param  TestResponse  $response  The HTTP response to check
     * @param  bool  $reportOnly  Whether to check for report-only header
     * @return $this
     */
    public function assertHasCspHeader(TestResponse $response, bool $reportOnly = false): self
    {
        $headerName = $reportOnly
            ? 'Content-Security-Policy-Report-Only'
            : 'Content-Security-Policy';

        $response->assertHeader($headerName);

        return $this;
    }

    /**
     * Assert that CSP contains a specific directive.
     *
     * @param  TestResponse  $response  The HTTP response to check
     * @param  string  $directive  The CSP directive to check (e.g., 'default-src', 'script-src')
     * @param  bool  $reportOnly  Whether to check report-only header
     * @return $this
     */
    public function assertCspContainsDirective(
        TestResponse $response,
        string $directive,
        bool $reportOnly = false
    ): self {
        $csp = $this->getCspHeader($response, $reportOnly);
        Assert::assertStringContainsString($directive, $csp, "CSP should contain '{$directive}' directive");

        return $this;
    }

    /**
     * Assert that a CSP directive contains a specific source.
     *
     * @param  TestResponse  $response  The HTTP response to check
     * @param  string  $directive  The CSP directive (e.g., 'script-src')
     * @param  string  $source  The source to check for (e.g., "'self'", 'https://example.com')
     * @param  bool  $reportOnly  Whether to check report-only header
     * @return $this
     */
    public function assertCspContainsSource(
        TestResponse $response,
        string $directive,
        string $source,
        bool $reportOnly = false
    ): self {
        $directives = $this->parseCspDirectives($response, $reportOnly);

        Assert::assertArrayHasKey($directive, $directives, "CSP should contain '{$directive}' directive");
        Assert::assertContains(
            $source,
            $directives[$directive],
            "CSP directive '{$directive}' should contain source '{$source}'"
        );

        return $this;
    }

    /**
     * Assert that a CSP directive does not contain a specific source.
     *
     * @param  TestResponse  $response  The HTTP response to check
     * @param  string  $directive  The CSP directive (e.g., 'script-src')
     * @param  string  $source  The source that should not be present
     * @param  bool  $reportOnly  Whether to check report-only header
     * @return $this
     */
    public function assertCspDoesNotContainSource(
        TestResponse $response,
        string $directive,
        string $source,
        bool $reportOnly = false
    ): self {
        $directives = $this->parseCspDirectives($response, $reportOnly);

        if (isset($directives[$directive])) {
            Assert::assertNotContains(
                $source,
                $directives[$directive],
                "CSP directive '{$directive}' should not contain source '{$source}'"
            );
        }

        return $this;
    }

    /**
     * Assert that CSP contains a nonce directive.
     *
     * @param  TestResponse  $response  The HTTP response to check
     * @param  string  $directive  The directive to check for nonce (default: 'script-src')
     * @param  bool  $reportOnly  Whether to check report-only header
     * @return $this
     */
    public function assertHasCspNonce(
        TestResponse $response,
        string $directive = 'script-src',
        bool $reportOnly = false
    ): self {
        $csp = $this->getCspHeader($response, $reportOnly);
        $pattern = "/{$directive}[^;]*'nonce-[A-Za-z0-9+\/=]+'/";

        Assert::assertMatchesRegularExpression(
            $pattern,
            $csp,
            "CSP '{$directive}' should contain a nonce directive"
        );

        return $this;
    }

    /**
     * Assert that CSP does not use 'unsafe-inline' in specified directive.
     *
     * @param  TestResponse  $response  The HTTP response to check
     * @param  string  $directive  The directive to check (default: 'script-src')
     * @param  bool  $reportOnly  Whether to check report-only header
     * @return $this
     */
    public function assertNoCspUnsafeInline(
        TestResponse $response,
        string $directive = 'script-src',
        bool $reportOnly = false
    ): self {
        return $this->assertCspDoesNotContainSource($response, $directive, "'unsafe-inline'", $reportOnly);
    }

    /**
     * Assert that CSP does not use 'unsafe-eval' in specified directive.
     *
     * @param  TestResponse  $response  The HTTP response to check
     * @param  string  $directive  The directive to check (default: 'script-src')
     * @param  bool  $reportOnly  Whether to check report-only header
     * @return $this
     */
    public function assertNoCspUnsafeEval(
        TestResponse $response,
        string $directive = 'script-src',
        bool $reportOnly = false
    ): self {
        return $this->assertCspDoesNotContainSource($response, $directive, "'unsafe-eval'", $reportOnly);
    }

    /**
     * Assert that Permissions-Policy header is present.
     *
     * @param  TestResponse  $response  The HTTP response to check
     * @return $this
     */
    public function assertHasPermissionsPolicy(TestResponse $response): self
    {
        $response->assertHeader('Permissions-Policy');

        return $this;
    }

    /**
     * Assert that Permissions-Policy contains a specific feature setting.
     *
     * @param  TestResponse  $response  The HTTP response to check
     * @param  string  $feature  The feature name (e.g., 'geolocation', 'camera')
     * @param  array<string>  $allowList  Expected allow list (empty array for '()')
     * @return $this
     */
    public function assertPermissionsPolicyFeature(
        TestResponse $response,
        string $feature,
        array $allowList = []
    ): self {
        $policy = $response->headers->get('Permissions-Policy');
        Assert::assertNotNull($policy, 'Permissions-Policy header should be present');

        if (empty($allowList)) {
            // Feature should be disabled: feature=()
            Assert::assertMatchesRegularExpression(
                "/{$feature}=\(\)/",
                $policy,
                "Permissions-Policy should disable '{$feature}'"
            );
        } else {
            // Feature should have specific origins
            Assert::assertStringContainsString(
                "{$feature}=",
                $policy,
                "Permissions-Policy should contain '{$feature}' feature"
            );
        }

        return $this;
    }

    /**
     * Assert that X-Frame-Options header is present with expected value.
     *
     * @param  TestResponse  $response  The HTTP response to check
     * @param  string|null  $expected  Expected value ('DENY', 'SAMEORIGIN', etc.)
     * @return $this
     */
    public function assertHasXFrameOptions(TestResponse $response, ?string $expected = null): self
    {
        $response->assertHeader('X-Frame-Options');

        if ($expected !== null) {
            $actual = $response->headers->get('X-Frame-Options');
            Assert::assertSame($expected, $actual, "X-Frame-Options should be '{$expected}'");
        }

        return $this;
    }

    /**
     * Assert that X-Content-Type-Options header is present with 'nosniff'.
     *
     * @param  TestResponse  $response  The HTTP response to check
     * @return $this
     */
    public function assertHasXContentTypeOptions(TestResponse $response): self
    {
        $response->assertHeader('X-Content-Type-Options', 'nosniff');

        return $this;
    }

    /**
     * Assert that Referrer-Policy header is present with expected value.
     *
     * @param  TestResponse  $response  The HTTP response to check
     * @param  string|null  $expected  Expected value (e.g., 'strict-origin-when-cross-origin')
     * @return $this
     */
    public function assertHasReferrerPolicy(TestResponse $response, ?string $expected = null): self
    {
        $response->assertHeader('Referrer-Policy');

        if ($expected !== null) {
            $actual = $response->headers->get('Referrer-Policy');
            Assert::assertSame($expected, $actual, "Referrer-Policy should be '{$expected}'");
        }

        return $this;
    }

    /**
     * Assert that X-XSS-Protection header is present.
     *
     * @param  TestResponse  $response  The HTTP response to check
     * @param  string|null  $expected  Expected value (e.g., '1; mode=block')
     * @return $this
     */
    public function assertHasXssProtection(TestResponse $response, ?string $expected = null): self
    {
        $response->assertHeader('X-XSS-Protection');

        if ($expected !== null) {
            $actual = $response->headers->get('X-XSS-Protection');
            Assert::assertSame($expected, $actual, "X-XSS-Protection should be '{$expected}'");
        }

        return $this;
    }

    /**
     * Assert that a header is NOT present.
     *
     * @param  TestResponse  $response  The HTTP response to check
     * @param  string  $headerName  The header name to check
     * @return $this
     */
    public function assertHeaderMissing(TestResponse $response, string $headerName): self
    {
        $response->assertHeaderMissing($headerName);

        return $this;
    }

    /**
     * Get the CSP header value from response.
     *
     * @param  TestResponse  $response  The HTTP response
     * @param  bool  $reportOnly  Whether to get report-only header
     * @return string The CSP header value
     */
    protected function getCspHeader(TestResponse $response, bool $reportOnly = false): string
    {
        $headerName = $reportOnly
            ? 'Content-Security-Policy-Report-Only'
            : 'Content-Security-Policy';

        $csp = $response->headers->get($headerName);
        Assert::assertNotNull($csp, "{$headerName} header should be present");

        return $csp;
    }

    /**
     * Parse CSP header into directives array.
     *
     * @param  TestResponse  $response  The HTTP response
     * @param  bool  $reportOnly  Whether to parse report-only header
     * @return array<string, array<string>> Map of directive to sources
     */
    protected function parseCspDirectives(TestResponse $response, bool $reportOnly = false): array
    {
        $csp = $this->getCspHeader($response, $reportOnly);
        $directives = [];

        foreach (explode(';', $csp) as $part) {
            $part = trim($part);
            if (empty($part)) {
                continue;
            }

            $tokens = preg_split('/\s+/', $part);
            $directiveName = array_shift($tokens);
            $directives[$directiveName] = $tokens;
        }

        return $directives;
    }

    /**
     * Extract the nonce value from a CSP header.
     *
     * @param  TestResponse  $response  The HTTP response
     * @param  string  $directive  The directive to extract nonce from
     * @param  bool  $reportOnly  Whether to check report-only header
     * @return string|null The nonce value or null if not found
     */
    public function extractCspNonce(
        TestResponse $response,
        string $directive = 'script-src',
        bool $reportOnly = false
    ): ?string {
        $csp = $this->getCspHeader($response, $reportOnly);

        // Match nonce in the specified directive
        if (preg_match("/{$directive}[^;]*'nonce-([A-Za-z0-9+\/=]+)'/", $csp, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
