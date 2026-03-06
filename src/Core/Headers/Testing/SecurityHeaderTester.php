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
use Symfony\Component\HttpFoundation\Response;

/**
 * Standalone helper for testing security headers.
 *
 * Provides static methods for validating security headers in HTTP responses.
 * Can be used without traits for more flexible testing scenarios.
 *
 * ## Usage
 *
 * ```php
 * use Core\Headers\Testing\SecurityHeaderTester;
 *
 * // Validate all security headers at once
 * $issues = SecurityHeaderTester::validate($response);
 * $this->assertEmpty($issues, 'Security headers should be valid');
 *
 * // Generate a security report
 * $report = SecurityHeaderTester::report($response);
 *
 * // Check specific headers
 * $this->assertTrue(SecurityHeaderTester::hasValidHsts($response));
 * $this->assertTrue(SecurityHeaderTester::hasValidCsp($response));
 * ```
 *
 * ## Validation Rules
 *
 * The validator checks against security best practices:
 * - HSTS: max-age >= 31536000 (1 year), includeSubDomains, preload
 * - CSP: No 'unsafe-inline' or 'unsafe-eval' in script-src/style-src
 * - X-Frame-Options: Should be DENY or SAMEORIGIN
 * - X-Content-Type-Options: Should be nosniff
 * - Referrer-Policy: Should be strict-origin-when-cross-origin or stricter
 */
class SecurityHeaderTester
{
    /**
     * Recommended minimum HSTS max-age (1 year).
     */
    public const RECOMMENDED_HSTS_MAX_AGE = 31536000;

    /**
     * Valid X-Frame-Options values.
     *
     * @var array<string>
     */
    public const VALID_X_FRAME_OPTIONS = ['DENY', 'SAMEORIGIN'];

    /**
     * Strict referrer policies (recommended).
     *
     * @var array<string>
     */
    public const STRICT_REFERRER_POLICIES = [
        'no-referrer',
        'no-referrer-when-downgrade',
        'same-origin',
        'strict-origin',
        'strict-origin-when-cross-origin',
    ];

    /**
     * Validate all security headers and return any issues found.
     *
     * @param  TestResponse|Response  $response  The HTTP response to validate
     * @param  array<string, mixed>  $options  Validation options
     * @return array<string, string> Map of header name to issue description
     */
    public static function validate(TestResponse|Response $response, array $options = []): array
    {
        $issues = [];
        $headers = self::getHeaders($response);

        // Check required headers
        $requiredHeaders = $options['required'] ?? [
            'X-Content-Type-Options',
            'X-Frame-Options',
            'Referrer-Policy',
        ];

        foreach ($requiredHeaders as $header) {
            if (! isset($headers[strtolower($header)])) {
                $issues[$header] = 'Header is missing';
            }
        }

        // Validate specific headers
        if ($issue = self::validateXContentTypeOptions($headers)) {
            $issues['X-Content-Type-Options'] = $issue;
        }

        if ($issue = self::validateXFrameOptions($headers)) {
            $issues['X-Frame-Options'] = $issue;
        }

        if ($issue = self::validateReferrerPolicy($headers)) {
            $issues['Referrer-Policy'] = $issue;
        }

        if (($options['check_hsts'] ?? true) && isset($headers['strict-transport-security'])) {
            if ($issue = self::validateHsts($headers)) {
                $issues['Strict-Transport-Security'] = $issue;
            }
        }

        if (($options['check_csp'] ?? true) && (isset($headers['content-security-policy']) || isset($headers['content-security-policy-report-only']))) {
            $cspIssues = self::validateCsp($headers, $options);
            foreach ($cspIssues as $directive => $issue) {
                $issues["CSP:{$directive}"] = $issue;
            }
        }

        if (($options['check_permissions'] ?? true) && isset($headers['permissions-policy'])) {
            if ($issue = self::validatePermissionsPolicy($headers)) {
                $issues['Permissions-Policy'] = $issue;
            }
        }

        return $issues;
    }

    /**
     * Generate a comprehensive security header report.
     *
     * @param  TestResponse|Response  $response  The HTTP response to analyze
     * @return array<string, mixed> Detailed report of security header status
     */
    public static function report(TestResponse|Response $response): array
    {
        $headers = self::getHeaders($response);

        return [
            'hsts' => self::analyzeHsts($headers),
            'csp' => self::analyzeCsp($headers),
            'permissions_policy' => self::analyzePermissionsPolicy($headers),
            'x_frame_options' => self::analyzeXFrameOptions($headers),
            'x_content_type_options' => self::analyzeXContentTypeOptions($headers),
            'referrer_policy' => self::analyzeReferrerPolicy($headers),
            'x_xss_protection' => self::analyzeXssProtection($headers),
            'issues' => self::validate($response),
            'score' => self::calculateScore($response),
        ];
    }

    /**
     * Calculate a security score (0-100) based on headers.
     *
     * @param  TestResponse|Response  $response  The HTTP response to score
     * @return int Security score from 0 (no security) to 100 (excellent)
     */
    public static function calculateScore(TestResponse|Response $response): int
    {
        $headers = self::getHeaders($response);
        $score = 0;

        // HSTS (20 points)
        if (isset($headers['strict-transport-security'])) {
            $score += 10;
            $hsts = $headers['strict-transport-security'];
            if (str_contains($hsts, 'includeSubDomains')) {
                $score += 5;
            }
            if (str_contains($hsts, 'preload')) {
                $score += 5;
            }
        }

        // CSP (30 points)
        $cspHeader = $headers['content-security-policy'] ?? $headers['content-security-policy-report-only'] ?? null;
        if ($cspHeader) {
            $score += 15;
            if (! str_contains($cspHeader, "'unsafe-inline'")) {
                $score += 10;
            }
            if (! str_contains($cspHeader, "'unsafe-eval'")) {
                $score += 5;
            }
        }

        // Permissions-Policy (10 points)
        if (isset($headers['permissions-policy'])) {
            $score += 10;
        }

        // X-Frame-Options (15 points)
        if (isset($headers['x-frame-options'])) {
            $score += 10;
            if (in_array(strtoupper($headers['x-frame-options']), self::VALID_X_FRAME_OPTIONS, true)) {
                $score += 5;
            }
        }

        // X-Content-Type-Options (10 points)
        if (isset($headers['x-content-type-options']) && strtolower($headers['x-content-type-options']) === 'nosniff') {
            $score += 10;
        }

        // Referrer-Policy (10 points)
        if (isset($headers['referrer-policy'])) {
            $score += 5;
            if (in_array(strtolower($headers['referrer-policy']), self::STRICT_REFERRER_POLICIES, true)) {
                $score += 5;
            }
        }

        // X-XSS-Protection (5 points - legacy but still good to have)
        if (isset($headers['x-xss-protection'])) {
            $score += 5;
        }

        return min(100, $score);
    }

    /**
     * Check if HSTS header is valid.
     *
     * @param  TestResponse|Response  $response  The HTTP response to check
     * @return bool True if HSTS is properly configured
     */
    public static function hasValidHsts(TestResponse|Response $response): bool
    {
        $headers = self::getHeaders($response);

        return self::validateHsts($headers) === null;
    }

    /**
     * Check if CSP header is valid (no unsafe directives).
     *
     * @param  TestResponse|Response  $response  The HTTP response to check
     * @param  array<string, mixed>  $options  Validation options
     * @return bool True if CSP is properly configured
     */
    public static function hasValidCsp(TestResponse|Response $response, array $options = []): bool
    {
        $headers = self::getHeaders($response);
        $issues = self::validateCsp($headers, $options);

        return empty($issues);
    }

    /**
     * Parse CSP header into directives.
     *
     * @param  TestResponse|Response  $response  The HTTP response
     * @return array<string, array<string>> Map of directive to sources
     */
    public static function parseCsp(TestResponse|Response $response): array
    {
        $headers = self::getHeaders($response);
        $csp = $headers['content-security-policy'] ?? $headers['content-security-policy-report-only'] ?? '';

        return self::parseCspString($csp);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // Internal validation methods
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * Get headers from response as lowercase key array.
     *
     * @return array<string, string>
     */
    protected static function getHeaders(TestResponse|Response $response): array
    {
        $headers = [];

        if ($response instanceof TestResponse) {
            $headerBag = $response->headers;
        } else {
            $headerBag = $response->headers;
        }

        foreach ($headerBag->all() as $name => $values) {
            $headers[strtolower($name)] = is_array($values) ? ($values[0] ?? '') : $values;
        }

        return $headers;
    }

    /**
     * Validate X-Content-Type-Options header.
     */
    protected static function validateXContentTypeOptions(array $headers): ?string
    {
        $value = $headers['x-content-type-options'] ?? null;

        if ($value === null) {
            return null; // Handled by required check
        }

        if (strtolower($value) !== 'nosniff') {
            return "Should be 'nosniff', got '{$value}'";
        }

        return null;
    }

    /**
     * Validate X-Frame-Options header.
     */
    protected static function validateXFrameOptions(array $headers): ?string
    {
        $value = $headers['x-frame-options'] ?? null;

        if ($value === null) {
            return null; // Handled by required check
        }

        if (! in_array(strtoupper($value), self::VALID_X_FRAME_OPTIONS, true)) {
            return "Should be DENY or SAMEORIGIN, got '{$value}'";
        }

        return null;
    }

    /**
     * Validate Referrer-Policy header.
     */
    protected static function validateReferrerPolicy(array $headers): ?string
    {
        $value = $headers['referrer-policy'] ?? null;

        if ($value === null) {
            return null; // Handled by required check
        }

        if (strtolower($value) === 'unsafe-url') {
            return "'unsafe-url' exposes full URL to third parties";
        }

        return null;
    }

    /**
     * Validate Strict-Transport-Security header.
     */
    protected static function validateHsts(array $headers): ?string
    {
        $value = $headers['strict-transport-security'] ?? null;

        if ($value === null) {
            return 'HSTS header is missing';
        }

        if (! preg_match('/max-age=(\d+)/', $value, $matches)) {
            return 'HSTS should contain max-age directive';
        }

        $maxAge = (int) $matches[1];
        if ($maxAge < self::RECOMMENDED_HSTS_MAX_AGE) {
            return 'max-age should be at least '.self::RECOMMENDED_HSTS_MAX_AGE." (1 year), got {$maxAge}";
        }

        return null;
    }

    /**
     * Validate Content-Security-Policy header.
     *
     * @return array<string, string> Map of directive to issue
     */
    protected static function validateCsp(array $headers, array $options = []): array
    {
        $csp = $headers['content-security-policy'] ?? $headers['content-security-policy-report-only'] ?? null;

        if ($csp === null) {
            return [];
        }

        $issues = [];
        $allowUnsafeInline = $options['allow_unsafe_inline'] ?? false;
        $allowUnsafeEval = $options['allow_unsafe_eval'] ?? false;

        $directives = self::parseCspString($csp);

        // Check for unsafe-inline in script-src
        if (! $allowUnsafeInline && isset($directives['script-src'])) {
            if (in_array("'unsafe-inline'", $directives['script-src'], true)) {
                $issues['script-src'] = "'unsafe-inline' allows XSS attacks";
            }
        }

        // Check for unsafe-eval in script-src
        if (! $allowUnsafeEval && isset($directives['script-src'])) {
            if (in_array("'unsafe-eval'", $directives['script-src'], true)) {
                $issues['script-src'] = ($issues['script-src'] ?? '')." 'unsafe-eval' allows code injection";
            }
        }

        return $issues;
    }

    /**
     * Validate Permissions-Policy header.
     */
    protected static function validatePermissionsPolicy(array $headers): ?string
    {
        $value = $headers['permissions-policy'] ?? null;

        if ($value === null) {
            return 'Permissions-Policy header is missing';
        }

        // Basic syntax check
        if (empty(trim($value))) {
            return 'Permissions-Policy header is empty';
        }

        return null;
    }

    /**
     * Parse CSP string into directives array.
     *
     * @return array<string, array<string>>
     */
    protected static function parseCspString(string $csp): array
    {
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

    // ─────────────────────────────────────────────────────────────────────────────
    // Analysis methods for report generation
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * Analyze HSTS header for report.
     */
    protected static function analyzeHsts(array $headers): array
    {
        $value = $headers['strict-transport-security'] ?? null;

        if ($value === null) {
            return ['present' => false, 'value' => null];
        }

        preg_match('/max-age=(\d+)/', $value, $matches);

        return [
            'present' => true,
            'value' => $value,
            'max_age' => isset($matches[1]) ? (int) $matches[1] : null,
            'include_subdomains' => str_contains($value, 'includeSubDomains'),
            'preload' => str_contains($value, 'preload'),
        ];
    }

    /**
     * Analyze CSP header for report.
     */
    protected static function analyzeCsp(array $headers): array
    {
        $csp = $headers['content-security-policy'] ?? null;
        $reportOnly = $headers['content-security-policy-report-only'] ?? null;

        if ($csp === null && $reportOnly === null) {
            return ['present' => false, 'value' => null, 'report_only' => null];
        }

        $value = $csp ?? $reportOnly;
        $directives = self::parseCspString($value);

        return [
            'present' => true,
            'report_only' => $csp === null,
            'value' => $value,
            'directives' => $directives,
            'has_nonce' => (bool) preg_match("/'nonce-/", $value),
            'has_unsafe_inline' => str_contains($value, "'unsafe-inline'"),
            'has_unsafe_eval' => str_contains($value, "'unsafe-eval'"),
        ];
    }

    /**
     * Analyze Permissions-Policy header for report.
     */
    protected static function analyzePermissionsPolicy(array $headers): array
    {
        $value = $headers['permissions-policy'] ?? null;

        if ($value === null) {
            return ['present' => false, 'value' => null];
        }

        // Parse features
        $features = [];
        preg_match_all('/(\w+(?:-\w+)*)=\(([^)]*)\)/', $value, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $features[$match[1]] = trim($match[2]) === '' ? [] : preg_split('/\s+/', trim($match[2]));
        }

        return [
            'present' => true,
            'value' => $value,
            'features' => $features,
        ];
    }

    /**
     * Analyze X-Frame-Options header for report.
     */
    protected static function analyzeXFrameOptions(array $headers): array
    {
        $value = $headers['x-frame-options'] ?? null;

        return [
            'present' => $value !== null,
            'value' => $value,
            'valid' => $value !== null && in_array(strtoupper($value), self::VALID_X_FRAME_OPTIONS, true),
        ];
    }

    /**
     * Analyze X-Content-Type-Options header for report.
     */
    protected static function analyzeXContentTypeOptions(array $headers): array
    {
        $value = $headers['x-content-type-options'] ?? null;

        return [
            'present' => $value !== null,
            'value' => $value,
            'valid' => $value !== null && strtolower($value) === 'nosniff',
        ];
    }

    /**
     * Analyze Referrer-Policy header for report.
     */
    protected static function analyzeReferrerPolicy(array $headers): array
    {
        $value = $headers['referrer-policy'] ?? null;

        return [
            'present' => $value !== null,
            'value' => $value,
            'strict' => $value !== null && in_array(strtolower($value), self::STRICT_REFERRER_POLICIES, true),
        ];
    }

    /**
     * Analyze X-XSS-Protection header for report.
     */
    protected static function analyzeXssProtection(array $headers): array
    {
        $value = $headers['x-xss-protection'] ?? null;

        return [
            'present' => $value !== null,
            'value' => $value,
            'enabled' => $value !== null && str_starts_with($value, '1'),
            'mode_block' => $value !== null && str_contains($value, 'mode=block'),
        ];
    }
}
