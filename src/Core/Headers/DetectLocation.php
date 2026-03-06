<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Headers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * GeoIP service for IP geolocation.
 *
 * Supports multiple data sources:
 * - CloudFlare headers (CF-IPCountry, CF-IPCity)
 * - Custom headers (X-Country-Code, X-Region)
 * - MaxMind GeoLite2 database (via torann/geoip package)
 * - Fallback to null if unavailable
 *
 * Results are cached to reduce lookups.
 */
class DetectLocation
{
    protected const CACHE_TTL = 86400; // 24 hours

    /**
     * Get full geo data for an IP address.
     *
     * @return array{country_code: ?string, region: ?string, city: ?string}
     */
    public function lookup(?string $ip, ?Request $request = null): array
    {
        if (! $ip || $this->isPrivateIp($ip)) {
            return $this->emptyResult();
        }

        // Check for CDN/proxy headers first (faster, no lookup needed)
        if ($request) {
            $headerResult = $this->lookupFromHeaders($request);
            if ($headerResult['country_code']) {
                return $headerResult;
            }
        }

        // Cache results to avoid repeated lookups
        return Cache::remember(
            "geoip:{$ip}",
            self::CACHE_TTL,
            fn () => $this->lookupFromDatabase($ip)
        );
    }

    /**
     * Get just the country code for an IP.
     */
    public function getCountryCode(?string $ip, ?Request $request = null): ?string
    {
        return $this->lookup($ip, $request)['country_code'];
    }

    /**
     * Get country and region for an IP.
     */
    public function getRegion(?string $ip, ?Request $request = null): ?string
    {
        return $this->lookup($ip, $request)['region'];
    }

    /**
     * Get city for an IP.
     */
    public function getCity(?string $ip, ?Request $request = null): ?string
    {
        return $this->lookup($ip, $request)['city'];
    }

    /**
     * Look up geo data from CDN/proxy headers.
     *
     * Supported headers:
     * - CloudFlare: CF-IPCountry, CF-IPCity, CF-IPRegion
     * - Custom: X-Country-Code, X-Region, X-City
     */
    protected function lookupFromHeaders(Request $request): array
    {
        // CloudFlare headers take priority
        $country = $request->header('CF-IPCountry')
            ?? $request->header('X-Country-Code');

        $region = $request->header('CF-IPRegion')
            ?? $request->header('X-Region');

        $city = $request->header('CF-IPCity')
            ?? $request->header('X-City');

        // CloudFlare uses 'XX' for unknown countries
        if ($country === 'XX') {
            $country = null;
        }

        return [
            'country_code' => $country,
            'region' => $region,
            'city' => $city,
        ];
    }

    /**
     * Look up geo data from GeoIP database.
     *
     * Uses torann/geoip package if available, otherwise returns null.
     */
    protected function lookupFromDatabase(string $ip): array
    {
        // Check if geoip() helper is available (torann/geoip package)
        if (function_exists('geoip')) {
            try {
                $location = geoip($ip);

                return [
                    'country_code' => $location->iso_code ?? null,
                    'region' => $location->state_name ?? $location->state ?? null,
                    'city' => $location->city ?? null,
                ];
            } catch (\Exception) {
                // GeoIP lookup failed, return empty
            }
        }

        return $this->emptyResult();
    }

    /**
     * Check if an IP is private/internal.
     */
    protected function isPrivateIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }

    /**
     * Return empty result array.
     */
    protected function emptyResult(): array
    {
        return [
            'country_code' => null,
            'region' => null,
            'city' => null,
        ];
    }
}
