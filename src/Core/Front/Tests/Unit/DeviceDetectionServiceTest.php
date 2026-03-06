<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Front\Tests\Unit;

use Core\Front\Services\DeviceDetectionService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DeviceDetectionServiceTest extends TestCase
{
    private DeviceDetectionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DeviceDetectionService;
    }

    // -------------------------------------------------------------------------
    // In-App Browser Detection
    // -------------------------------------------------------------------------

    #[Test]
    #[DataProvider('inAppBrowserUserAgents')]
    public function it_detects_in_app_browsers(string $userAgent, string $expectedPlatform): void
    {
        $this->assertSame($expectedPlatform, $this->service->detectInAppBrowser($userAgent));
        $this->assertTrue($this->service->isInAppBrowser($userAgent));
    }

    public static function inAppBrowserUserAgents(): array
    {
        return [
            'Instagram iOS' => [
                'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 Instagram 275.0.0.20.98',
                'instagram',
            ],
            'Instagram Android' => [
                'Mozilla/5.0 (Linux; Android 13) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Mobile Safari/537.36 Instagram 290.0.0.30.115',
                'instagram',
            ],
            'Facebook iOS' => [
                'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/20A362 [FBAN/FBIOS;FBAV/420.0.0.42.70]',
                'facebook',
            ],
            'Facebook Android' => [
                'Mozilla/5.0 (Linux; Android 13) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Mobile Safari/537.36 [FB_IAB/FB4A;FBAV/420.0.0.42.70]',
                'facebook',
            ],
            'TikTok iOS' => [
                'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/20A362 BytedanceWebview/d8a21c6',
                'tiktok',
            ],
            'TikTok Android' => [
                'Mozilla/5.0 (Linux; Android 13) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Mobile Safari/537.36 musical_ly/27.7.4',
                'tiktok',
            ],
            'Twitter iOS' => [
                'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/20A362 Twitter for iPhone',
                'twitter',
            ],
            'LinkedIn iOS' => [
                'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/20A362 LinkedInApp',
                'linkedin',
            ],
            'Snapchat iOS' => [
                'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/20A362 Snapchat/12.0.0',
                'snapchat',
            ],
            'Threads (Barcelona)' => [
                'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/20A362 Barcelona',
                'threads',
            ],
            'Pinterest iOS' => [
                'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/20A362 Pinterest for iOS',
                'pinterest',
            ],
            'Reddit iOS' => [
                'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/20A362 Reddit/2023.30.0',
                'reddit',
            ],
            'WeChat' => [
                'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/20A362 MicroMessenger/8.0.38',
                'wechat',
            ],
            'WhatsApp' => [
                'WhatsApp/2.23.17.76 A',
                'whatsapp',
            ],
        ];
    }

    #[Test]
    public function it_returns_null_for_standard_browsers(): void
    {
        $standardBrowsers = [
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/115.0',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1',
        ];

        foreach ($standardBrowsers as $ua) {
            $this->assertNull($this->service->detectInAppBrowser($ua));
            $this->assertFalse($this->service->isInAppBrowser($ua));
        }
    }

    // -------------------------------------------------------------------------
    // Platform-Specific Methods
    // -------------------------------------------------------------------------

    #[Test]
    public function it_has_platform_specific_methods(): void
    {
        $instagramUa = 'Mozilla/5.0 (iPhone) Instagram 275.0.0.20.98';
        $facebookUa = 'Mozilla/5.0 (iPhone) [FBAN/FBIOS]';
        $tiktokUa = 'Mozilla/5.0 (iPhone) BytedanceWebview';
        $twitterUa = 'Mozilla/5.0 (iPhone) Twitter for iPhone';

        $this->assertTrue($this->service->isInstagram($instagramUa));
        $this->assertFalse($this->service->isInstagram($facebookUa));

        $this->assertTrue($this->service->isFacebook($facebookUa));
        $this->assertFalse($this->service->isFacebook($instagramUa));

        $this->assertTrue($this->service->isTikTok($tiktokUa));
        $this->assertTrue($this->service->isTwitter($twitterUa));
    }

    // -------------------------------------------------------------------------
    // Strict Content Platforms
    // -------------------------------------------------------------------------

    #[Test]
    public function it_identifies_strict_content_platforms(): void
    {
        // Strict platforms
        $this->assertTrue($this->service->isStrictContentPlatform('Instagram'));
        $this->assertTrue($this->service->isStrictContentPlatform('[FBAN/FBIOS]'));
        $this->assertTrue($this->service->isStrictContentPlatform('TikTok'));
        $this->assertTrue($this->service->isStrictContentPlatform('Twitter'));
        $this->assertTrue($this->service->isStrictContentPlatform('Snapchat'));
        $this->assertTrue($this->service->isStrictContentPlatform('LinkedInApp'));
        $this->assertTrue($this->service->isStrictContentPlatform('Barcelona')); // Threads

        // Non-strict platforms
        $this->assertFalse($this->service->isStrictContentPlatform('Pinterest'));
        $this->assertFalse($this->service->isStrictContentPlatform('Reddit'));
        $this->assertFalse($this->service->isStrictContentPlatform('Chrome'));
    }

    #[Test]
    public function it_identifies_meta_platforms(): void
    {
        $this->assertTrue($this->service->isMetaPlatform('Instagram'));
        $this->assertTrue($this->service->isMetaPlatform('[FBAN/FBIOS]'));
        $this->assertTrue($this->service->isMetaPlatform('Barcelona')); // Threads

        $this->assertFalse($this->service->isMetaPlatform('TikTok'));
        $this->assertFalse($this->service->isMetaPlatform('Twitter'));
    }

    // -------------------------------------------------------------------------
    // Display Names
    // -------------------------------------------------------------------------

    #[Test]
    public function it_returns_platform_display_names(): void
    {
        $this->assertSame('Instagram', $this->service->getPlatformDisplayName('Instagram'));
        $this->assertSame('TikTok', $this->service->getPlatformDisplayName('TikTok'));
        $this->assertSame('X (Twitter)', $this->service->getPlatformDisplayName('Twitter'));
        $this->assertNull($this->service->getPlatformDisplayName('Chrome'));
    }

    // -------------------------------------------------------------------------
    // Device Detection
    // -------------------------------------------------------------------------

    #[Test]
    public function it_detects_device_types(): void
    {
        $this->assertSame('mobile', $this->service->detectDeviceType('iPhone'));
        $this->assertSame('mobile', $this->service->detectDeviceType('Android Mobile'));
        $this->assertSame('tablet', $this->service->detectDeviceType('iPad'));
        $this->assertSame('tablet', $this->service->detectDeviceType('Android Tablet'));
        $this->assertSame('desktop', $this->service->detectDeviceType('Windows NT 10.0'));
        $this->assertSame('desktop', $this->service->detectDeviceType('Macintosh'));
    }

    #[Test]
    public function it_detects_operating_systems(): void
    {
        $this->assertSame('iOS', $this->service->detectOS('iPhone'));
        $this->assertSame('iOS', $this->service->detectOS('iPad'));
        $this->assertSame('Android', $this->service->detectOS('Android'));
        $this->assertSame('Windows', $this->service->detectOS('Windows NT 10.0'));
        $this->assertSame('macOS', $this->service->detectOS('Macintosh'));
        $this->assertSame('Linux', $this->service->detectOS('Linux x86_64'));
    }

    #[Test]
    public function it_detects_browsers(): void
    {
        $this->assertSame('Chrome', $this->service->detectBrowser('Chrome/114.0.0.0'));
        $this->assertSame('Firefox', $this->service->detectBrowser('Firefox/115.0'));
        $this->assertSame('Safari', $this->service->detectBrowser('Safari/604.1'));
        $this->assertSame('Edge', $this->service->detectBrowser('Edg/114.0'));
    }

    #[Test]
    public function it_returns_null_browser_for_in_app(): void
    {
        $this->assertNull($this->service->detectBrowser('Instagram 275.0'));
    }

    // -------------------------------------------------------------------------
    // Bot Detection
    // -------------------------------------------------------------------------

    #[Test]
    public function it_detects_bots(): void
    {
        $this->assertTrue($this->service->isBot('Googlebot/2.1'));
        $this->assertTrue($this->service->isBot('Bingbot/2.0'));
        $this->assertTrue($this->service->isBot('facebookexternalhit/1.1'));
        $this->assertTrue($this->service->isBot('Twitterbot/1.0'));
        $this->assertTrue($this->service->isBot('AhrefsBot/7.0'));

        $this->assertFalse($this->service->isBot('Chrome/114.0.0.0'));
        $this->assertFalse($this->service->isBot('Instagram 275.0'));
    }

    // -------------------------------------------------------------------------
    // Full Parse
    // -------------------------------------------------------------------------

    #[Test]
    public function it_parses_full_device_info(): void
    {
        $ua = 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 Mobile Instagram 275.0.0.20.98';

        $result = $this->service->parse($ua);

        $this->assertSame('mobile', $result['device_type']);
        $this->assertSame('iOS', $result['os_name']);
        $this->assertNull($result['browser_name']); // In-app
        $this->assertSame('instagram', $result['in_app_browser']);
        $this->assertTrue($result['is_in_app']);
    }

    #[Test]
    public function it_handles_null_and_empty_user_agents(): void
    {
        $this->assertNull($this->service->detectInAppBrowser(null));
        $this->assertNull($this->service->detectInAppBrowser(''));
        $this->assertFalse($this->service->isInAppBrowser(null));
        $this->assertSame('desktop', $this->service->detectDeviceType(null));
    }
}
