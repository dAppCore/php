<?php

declare(strict_types=1);

namespace Core\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that a URL is safe for webhook delivery.
 *
 * Protects against SSRF by:
 * - Blocking localhost and loopback addresses (127.0.0.0/8, ::1)
 * - Blocking private networks (10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16)
 * - Blocking link-local addresses (169.254.0.0/16, fe80::/10)
 * - Blocking reserved ranges and special-use addresses
 * - Blocking local domain names (.local, .localhost, .internal)
 *
 * Optionally enforces specific allowed domains for known services.
 */
class SafeWebhookUrl implements ValidationRule
{
    /**
     * Known webhook domains for specific services.
     */
    protected const ALLOWED_DOMAINS = [
        'discord' => [
            'discord.com',
            'discordapp.com',
        ],
        'slack' => [
            'hooks.slack.com',
        ],
        'telegram' => [
            'api.telegram.org',
        ],
    ];

    /**
     * Create a new rule instance.
     *
     * @param  string|null  $service  Restrict to specific service domains (discord, slack, telegram)
     */
    public function __construct(
        protected ?string $service = null
    ) {}

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            return;
        }

        // Basic URL validation
        if (! filter_var($value, FILTER_VALIDATE_URL)) {
            $fail('The :attribute must be a valid URL.');

            return;
        }

        $parsed = parse_url($value);
        $host = $parsed['host'] ?? '';
        $scheme = $parsed['scheme'] ?? '';

        // Must be HTTPS for webhooks (security best practice)
        if ($scheme !== 'https') {
            $fail('The :attribute must use HTTPS.');

            return;
        }

        if (empty($host)) {
            $fail('The :attribute must contain a valid hostname.');

            return;
        }

        // If restricted to specific service, validate domain
        if ($this->service && isset(self::ALLOWED_DOMAINS[$this->service])) {
            $allowedDomains = self::ALLOWED_DOMAINS[$this->service];
            $hostLower = strtolower($host);

            $matched = false;
            foreach ($allowedDomains as $domain) {
                if ($hostLower === $domain || str_ends_with($hostLower, '.'.$domain)) {
                    $matched = true;
                    break;
                }
            }

            if (! $matched) {
                $serviceName = ucfirst($this->service);
                $fail("The :attribute must be a valid {$serviceName} webhook URL.");

                return;
            }

            // Known service domains are trusted, skip SSRF checks
            return;
        }

        // For custom webhooks, perform SSRF validation
        if ($this->isLocalHostname($host)) {
            $fail('The :attribute cannot point to localhost or local domains.');

            return;
        }

        // Check if it's an IP address
        $normalizedIp = $this->normalizeIpAddress($host);
        if ($normalizedIp !== null) {
            if ($this->isPrivateOrLocalhost($normalizedIp)) {
                $fail('The :attribute cannot point to localhost or private networks.');

                return;
            }
        }

        // Resolve hostname and check all IPs
        if ($normalizedIp === null) {
            $resolvedIps = $this->resolveHostname($host);

            foreach ($resolvedIps as $ip) {
                if ($this->isPrivateOrLocalhost($ip)) {
                    $fail('The :attribute resolves to a private or local address.');

                    return;
                }
            }
        }
    }

    /**
     * Check if a hostname is a local/private domain.
     */
    protected function isLocalHostname(string $host): bool
    {
        $host = strtolower(trim($host));

        if ($host === 'localhost') {
            return true;
        }

        $localSuffixes = ['.local', '.localhost', '.internal', '.localdomain', '.home.arpa'];

        foreach ($localSuffixes as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalize an IP address to canonical form.
     */
    protected function normalizeIpAddress(string $host): ?string
    {
        $host = trim($host);

        // Handle bracketed IPv6
        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            $host = substr($host, 1, -1);
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $packed = @inet_pton($host);
            if ($packed !== false) {
                return inet_ntop($packed);
            }

            return $host;
        }

        // Handle decimal IP (e.g., 2130706433 for 127.0.0.1)
        if (preg_match('/^\d+$/', $host)) {
            $decimal = filter_var($host, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 0, 'max_range' => 4294967295],
            ]);
            if ($decimal !== false) {
                return long2ip($decimal);
            }
        }

        return null;
    }

    /**
     * Resolve hostname to IP addresses.
     */
    protected function resolveHostname(string $host): array
    {
        $ips = [];

        $ipv4Records = @dns_get_record($host, DNS_A);
        if (is_array($ipv4Records)) {
            foreach ($ipv4Records as $record) {
                if (isset($record['ip'])) {
                    $ips[] = $record['ip'];
                }
            }
        }

        $ipv6Records = @dns_get_record($host, DNS_AAAA);
        if (is_array($ipv6Records)) {
            foreach ($ipv6Records as $record) {
                if (isset($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }

        // Fallback
        if (empty($ips)) {
            $fallback = @gethostbynamel($host);
            if (is_array($fallback)) {
                $ips = $fallback;
            }
        }

        return $ips;
    }

    /**
     * Check if an IP address is localhost or private.
     */
    protected function isPrivateOrLocalhost(string $ip): bool
    {
        // IPv6 checks
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = @inet_pton($ip);
            if ($packed === false) {
                return true;
            }

            $normalized = inet_ntop($packed);

            if ($normalized === '::1') {
                return true;
            }

            // IPv4-mapped IPv6
            if (str_starts_with($normalized, '::ffff:')) {
                $ipv4 = substr($normalized, 7);
                if (filter_var($ipv4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    return $this->isPrivateIpv4($ipv4);
                }
            }

            return ! filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            );
        }

        // IPv4 checks
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $this->isPrivateIpv4($ip);
        }

        return true;
    }

    /**
     * Check if an IPv4 address is private or localhost.
     */
    protected function isPrivateIpv4(string $ip): bool
    {
        $long = ip2long($ip);
        if ($long === false) {
            return true;
        }

        // 127.0.0.0/8
        if (($long >> 24) === 127) {
            return true;
        }

        // 0.0.0.0/8
        if (($long >> 24) === 0) {
            return true;
        }

        return ! filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }
}
