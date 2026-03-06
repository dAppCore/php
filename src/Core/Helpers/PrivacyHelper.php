<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Helpers;

/**
 * Privacy helper for IP anonymisation and hashing.
 *
 * Provides multiple methods for anonymising IP addresses:
 * - Truncation: Zero out last octet(s) (GDPR-compliant, partially reversible)
 * - Hashing: SHA256 with salt (irreversible, good for unique detection)
 *
 * Used by Analytics Center and BioHost for consistent privacy handling.
 */
class PrivacyHelper
{
    /**
     * Anonymise an IP by zeroing the last octet (IPv4) or last 80 bits (IPv6).
     *
     * This is GDPR-compliant as the full IP cannot be recovered.
     * Example: 192.168.1.123 → 192.168.1.0
     */
    public static function anonymiseIp(?string $ip): ?string
    {
        if (! $ip) {
            return null;
        }

        // IPv4: Zero last octet
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            $parts[3] = '0';

            return implode('.', $parts);
        }

        // IPv6: Zero last 80 bits (keep first 48 bits / 3 groups)
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $expanded = self::expandIpv6($ip);
            $parts = explode(':', $expanded);

            // Zero last 5 groups (80 bits)
            for ($i = 3; $i < 8; $i++) {
                $parts[$i] = '0000';
            }

            return implode(':', $parts);
        }

        return null;
    }

    /**
     * Anonymise an IP more aggressively by zeroing last 2 octets.
     *
     * Example: 192.168.1.123 → 192.168.0.0
     */
    public static function anonymiseIpStrong(?string $ip): ?string
    {
        if (! $ip) {
            return null;
        }

        // IPv4: Zero last 2 octets
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            $parts[2] = '0';
            $parts[3] = '0';

            return implode('.', $parts);
        }

        // IPv6: Zero last 96 bits (keep first 32 bits / 2 groups)
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $expanded = self::expandIpv6($ip);
            $parts = explode(':', $expanded);

            // Zero last 6 groups (96 bits)
            for ($i = 2; $i < 8; $i++) {
                $parts[$i] = '0000';
            }

            return implode(':', $parts);
        }

        return null;
    }

    /**
     * Hash an IP with a daily-rotating salt for privacy-first unique detection.
     *
     * The hash changes each day, preventing long-term tracking while
     * still allowing same-day unique visitor detection.
     *
     * @param  string|null  $customSalt  Optional custom salt (defaults to app key + date)
     */
    public static function hashIpDaily(?string $ip, ?string $customSalt = null): ?string
    {
        if (! $ip) {
            return null;
        }

        $salt = $customSalt ?? (config('app.key').now()->format('Y-m-d'));

        return hash('sha256', $ip.$salt);
    }

    /**
     * Hash an IP with a static salt for consistent hashing.
     *
     * Use this when you need the same hash across days but still want
     * the IP to be irreversible.
     */
    public static function hashIp(?string $ip): ?string
    {
        if (! $ip) {
            return null;
        }

        return hash('sha256', $ip.config('app.key'));
    }

    /**
     * Generate a cache key for unique visitor detection.
     *
     * Combines a prefix with IP and date for same-day uniqueness.
     */
    public static function uniqueVisitorCacheKey(string $prefix, string $ip): string
    {
        return sprintf('%s:%s:%s', $prefix, $ip, now()->format('Y-m-d'));
    }

    /**
     * Check if an IP is private/internal (not routable on internet).
     */
    public static function isPrivateIp(?string $ip): bool
    {
        if (! $ip) {
            return true;
        }

        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }

    /**
     * Expand a shortened IPv6 address to full form.
     */
    protected static function expandIpv6(string $ip): string
    {
        // Handle :: shorthand
        if (str_contains($ip, '::')) {
            $parts = explode('::', $ip);
            $left = $parts[0] ? explode(':', $parts[0]) : [];
            $right = isset($parts[1]) && $parts[1] ? explode(':', $parts[1]) : [];
            $missing = 8 - count($left) - count($right);
            $middle = array_fill(0, $missing, '0000');
            $all = array_merge($left, $middle, $right);
        } else {
            $all = explode(':', $ip);
        }

        // Pad each group to 4 characters
        return implode(':', array_map(
            fn ($group) => str_pad($group, 4, '0', STR_PAD_LEFT),
            $all
        ));
    }
}
