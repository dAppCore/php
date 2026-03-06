<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Helpers;

use Illuminate\Http\Request;

/**
 * UTM helper for extracting marketing campaign parameters.
 *
 * Extracts UTM parameters from requests for attribution tracking.
 * Supports standard UTM params (source, medium, campaign, term, content).
 */
class UtmHelper
{
    /**
     * Standard UTM parameter names.
     */
    public const UTM_SOURCE = 'utm_source';

    public const UTM_MEDIUM = 'utm_medium';

    public const UTM_CAMPAIGN = 'utm_campaign';

    public const UTM_TERM = 'utm_term';

    public const UTM_CONTENT = 'utm_content';

    /**
     * All UTM parameter names.
     */
    public const ALL_PARAMS = [
        self::UTM_SOURCE,
        self::UTM_MEDIUM,
        self::UTM_CAMPAIGN,
        self::UTM_TERM,
        self::UTM_CONTENT,
    ];

    /**
     * Extract all UTM parameters from a request.
     *
     * @return array{utm_source: ?string, utm_medium: ?string, utm_campaign: ?string, utm_term: ?string, utm_content: ?string}
     */
    public static function extractFromRequest(Request $request): array
    {
        return [
            'utm_source' => self::sanitise($request->query(self::UTM_SOURCE)),
            'utm_medium' => self::sanitise($request->query(self::UTM_MEDIUM)),
            'utm_campaign' => self::sanitise($request->query(self::UTM_CAMPAIGN)),
            'utm_term' => self::sanitise($request->query(self::UTM_TERM)),
            'utm_content' => self::sanitise($request->query(self::UTM_CONTENT)),
        ];
    }

    /**
     * Extract UTM parameters from an array (e.g., serialised request data).
     *
     * @return array{utm_source: ?string, utm_medium: ?string, utm_campaign: ?string, utm_term: ?string, utm_content: ?string}
     */
    public static function extractFromArray(array $data): array
    {
        return [
            'utm_source' => self::sanitise($data['utm_source'] ?? null),
            'utm_medium' => self::sanitise($data['utm_medium'] ?? null),
            'utm_campaign' => self::sanitise($data['utm_campaign'] ?? null),
            'utm_term' => self::sanitise($data['utm_term'] ?? null),
            'utm_content' => self::sanitise($data['utm_content'] ?? null),
        ];
    }

    /**
     * Extract UTM parameters from a URL string.
     *
     * @return array{utm_source: ?string, utm_medium: ?string, utm_campaign: ?string, utm_term: ?string, utm_content: ?string}
     */
    public static function extractFromUrl(string $url): array
    {
        $query = parse_url($url, PHP_URL_QUERY);

        if (! $query) {
            return self::emptyResult();
        }

        parse_str($query, $params);

        return self::extractFromArray($params);
    }

    /**
     * Check if any UTM parameters are present in a request.
     */
    public static function hasUtmParams(Request $request): bool
    {
        foreach (self::ALL_PARAMS as $param) {
            if ($request->filled($param)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the referring source from UTM or referrer header.
     *
     * Falls back to extracting domain from referrer if no UTM source.
     */
    public static function getSource(Request $request): ?string
    {
        // Check UTM source first
        $utmSource = self::sanitise($request->query(self::UTM_SOURCE));
        if ($utmSource) {
            return $utmSource;
        }

        // Fall back to referrer domain
        return self::extractReferrerHost($request->header('Referer'));
    }

    /**
     * Extract host from a referrer URL.
     */
    public static function extractReferrerHost(?string $referrer): ?string
    {
        if (! $referrer) {
            return null;
        }

        $host = parse_url($referrer, PHP_URL_HOST);

        if (! $host) {
            return null;
        }

        // Remove www. prefix for cleaner data
        return preg_replace('/^www\./', '', $host);
    }

    /**
     * Sanitise a UTM parameter value.
     *
     * Trims whitespace, limits length, and removes potentially dangerous characters.
     */
    protected static function sanitise(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Trim and limit length
        $value = mb_substr(trim($value), 0, 255);

        // Remove any control characters
        $value = preg_replace('/[\x00-\x1F\x7F]/', '', $value);

        return $value ?: null;
    }

    /**
     * Return empty UTM result array.
     */
    protected static function emptyResult(): array
    {
        return [
            'utm_source' => null,
            'utm_medium' => null,
            'utm_campaign' => null,
            'utm_term' => null,
            'utm_content' => null,
        ];
    }
}
