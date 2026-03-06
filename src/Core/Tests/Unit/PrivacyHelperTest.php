<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Tests\Unit;

use Core\Helpers\PrivacyHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PrivacyHelperTest extends TestCase
{
    // -------------------------------------------------------------------------
    // IP Anonymisation (Standard)
    // -------------------------------------------------------------------------

    #[Test]
    public function it_anonymises_ipv4_by_zeroing_last_octet(): void
    {
        $this->assertSame('192.168.1.0', PrivacyHelper::anonymiseIp('192.168.1.123'));
        $this->assertSame('10.0.0.0', PrivacyHelper::anonymiseIp('10.0.0.255'));
        $this->assertSame('8.8.8.0', PrivacyHelper::anonymiseIp('8.8.8.8'));
    }

    #[Test]
    public function it_anonymises_ipv6_by_zeroing_last_80_bits(): void
    {
        $result = PrivacyHelper::anonymiseIp('2001:0db8:85a3:0000:0000:8a2e:0370:7334');

        // Should keep first 3 groups, zero the rest
        $this->assertStringStartsWith('2001:0db8:85a3:', $result);
        $this->assertStringEndsWith(':0000:0000:0000:0000:0000', $result);
    }

    #[Test]
    public function it_handles_abbreviated_ipv6(): void
    {
        $result = PrivacyHelper::anonymiseIp('2001:db8::1');

        $this->assertNotNull($result);
        // Should expand and anonymise
        $this->assertStringContainsString('2001', $result);
    }

    #[Test]
    public function it_returns_null_for_null_ip(): void
    {
        $this->assertNull(PrivacyHelper::anonymiseIp(null));
    }

    #[Test]
    public function it_returns_null_for_empty_ip(): void
    {
        $this->assertNull(PrivacyHelper::anonymiseIp(''));
    }

    #[Test]
    public function it_returns_null_for_invalid_ip(): void
    {
        $this->assertNull(PrivacyHelper::anonymiseIp('not-an-ip'));
        $this->assertNull(PrivacyHelper::anonymiseIp('999.999.999.999'));
    }

    // -------------------------------------------------------------------------
    // IP Anonymisation (Strong)
    // -------------------------------------------------------------------------

    #[Test]
    public function it_anonymises_ipv4_strongly_by_zeroing_last_two_octets(): void
    {
        $this->assertSame('192.168.0.0', PrivacyHelper::anonymiseIpStrong('192.168.1.123'));
        $this->assertSame('10.0.0.0', PrivacyHelper::anonymiseIpStrong('10.0.0.255'));
        $this->assertSame('8.8.0.0', PrivacyHelper::anonymiseIpStrong('8.8.8.8'));
    }

    #[Test]
    public function it_anonymises_ipv6_strongly_by_zeroing_last_96_bits(): void
    {
        $result = PrivacyHelper::anonymiseIpStrong('2001:0db8:85a3:0000:0000:8a2e:0370:7334');

        // Should keep first 2 groups, zero the rest
        $this->assertStringStartsWith('2001:0db8:', $result);
        $parts = explode(':', $result);
        // Last 6 groups should be zeroed
        for ($i = 2; $i < 8; $i++) {
            $this->assertSame('0000', $parts[$i]);
        }
    }

    #[Test]
    public function it_handles_null_for_strong_anonymisation(): void
    {
        $this->assertNull(PrivacyHelper::anonymiseIpStrong(null));
        $this->assertNull(PrivacyHelper::anonymiseIpStrong(''));
    }

    // -------------------------------------------------------------------------
    // IP Hashing (Daily)
    // -------------------------------------------------------------------------

    #[Test]
    public function it_hashes_ip_with_daily_rotation(): void
    {
        $hash = PrivacyHelper::hashIpDaily('192.168.1.1');

        $this->assertNotNull($hash);
        $this->assertSame(64, strlen($hash)); // SHA256 = 64 hex chars
    }

    #[Test]
    public function it_produces_same_hash_for_same_ip_on_same_day(): void
    {
        $hash1 = PrivacyHelper::hashIpDaily('192.168.1.1');
        $hash2 = PrivacyHelper::hashIpDaily('192.168.1.1');

        $this->assertSame($hash1, $hash2);
    }

    #[Test]
    public function it_produces_different_hashes_for_different_ips(): void
    {
        $hash1 = PrivacyHelper::hashIpDaily('192.168.1.1');
        $hash2 = PrivacyHelper::hashIpDaily('192.168.1.2');

        $this->assertNotSame($hash1, $hash2);
    }

    #[Test]
    public function it_accepts_custom_salt_for_daily_hash(): void
    {
        $hash1 = PrivacyHelper::hashIpDaily('192.168.1.1', 'custom-salt');
        $hash2 = PrivacyHelper::hashIpDaily('192.168.1.1', 'different-salt');

        $this->assertNotSame($hash1, $hash2);
    }

    #[Test]
    public function it_returns_null_for_null_ip_daily_hash(): void
    {
        $this->assertNull(PrivacyHelper::hashIpDaily(null));
    }

    // -------------------------------------------------------------------------
    // IP Hashing (Static)
    // -------------------------------------------------------------------------

    #[Test]
    public function it_hashes_ip_with_static_salt(): void
    {
        $hash = PrivacyHelper::hashIp('192.168.1.1');

        $this->assertNotNull($hash);
        $this->assertSame(64, strlen($hash));
    }

    #[Test]
    public function it_produces_consistent_static_hash(): void
    {
        $hash1 = PrivacyHelper::hashIp('192.168.1.1');
        $hash2 = PrivacyHelper::hashIp('192.168.1.1');

        $this->assertSame($hash1, $hash2);
    }

    #[Test]
    public function it_returns_null_for_null_ip_static_hash(): void
    {
        $this->assertNull(PrivacyHelper::hashIp(null));
    }

    // -------------------------------------------------------------------------
    // Unique Visitor Cache Key
    // -------------------------------------------------------------------------

    #[Test]
    public function it_generates_unique_visitor_cache_key(): void
    {
        $key = PrivacyHelper::uniqueVisitorCacheKey('page:123', '192.168.1.1');

        $this->assertStringStartsWith('page:123:', $key);
        $this->assertStringContainsString('192.168.1.1', $key);
        $this->assertStringContainsString(now()->format('Y-m-d'), $key);
    }

    #[Test]
    public function it_generates_same_key_for_same_visitor_same_day(): void
    {
        $key1 = PrivacyHelper::uniqueVisitorCacheKey('page:123', '192.168.1.1');
        $key2 = PrivacyHelper::uniqueVisitorCacheKey('page:123', '192.168.1.1');

        $this->assertSame($key1, $key2);
    }

    #[Test]
    public function it_generates_different_keys_for_different_prefixes(): void
    {
        $key1 = PrivacyHelper::uniqueVisitorCacheKey('page:123', '192.168.1.1');
        $key2 = PrivacyHelper::uniqueVisitorCacheKey('page:456', '192.168.1.1');

        $this->assertNotSame($key1, $key2);
    }

    // -------------------------------------------------------------------------
    // Private IP Detection
    // -------------------------------------------------------------------------

    #[Test]
    #[DataProvider('privateIpAddresses')]
    public function it_detects_private_ips(string $ip): void
    {
        $this->assertTrue(PrivacyHelper::isPrivateIp($ip));
    }

    public static function privateIpAddresses(): array
    {
        return [
            'localhost' => ['127.0.0.1'],
            'private 10.x.x.x' => ['10.0.0.1'],
            'private 172.16.x.x' => ['172.16.0.1'],
            'private 192.168.x.x' => ['192.168.1.1'],
            'link-local' => ['169.254.1.1'],
        ];
    }

    #[Test]
    #[DataProvider('publicIpAddresses')]
    public function it_detects_public_ips(string $ip): void
    {
        $this->assertFalse(PrivacyHelper::isPrivateIp($ip));
    }

    public static function publicIpAddresses(): array
    {
        return [
            'google dns' => ['8.8.8.8'],
            'cloudflare dns' => ['1.1.1.1'],
            'random public' => ['203.0.113.1'],
        ];
    }

    #[Test]
    public function it_treats_null_ip_as_private(): void
    {
        $this->assertTrue(PrivacyHelper::isPrivateIp(null));
    }

    #[Test]
    public function it_treats_empty_ip_as_private(): void
    {
        $this->assertTrue(PrivacyHelper::isPrivateIp(''));
    }

    // -------------------------------------------------------------------------
    // Edge Cases
    // -------------------------------------------------------------------------

    #[Test]
    public function it_handles_ipv4_mapped_ipv6(): void
    {
        // ::ffff:192.168.1.1 is IPv4-mapped IPv6
        $result = PrivacyHelper::anonymiseIp('::ffff:192.168.1.1');

        // Should be handled as IPv6
        $this->assertNotNull($result);
    }

    #[Test]
    public function it_handles_loopback_ipv6(): void
    {
        $result = PrivacyHelper::anonymiseIp('::1');

        $this->assertNotNull($result);
    }
}
