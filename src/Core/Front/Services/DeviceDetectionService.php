<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Front\Services;

/**
 * Device and in-app browser detection.
 *
 * Detects device type, OS, browser, and crucially - in-app browsers
 * from social media platforms. Essential for creators who need to
 * handle strict content platform traffic differently.
 */
class DeviceDetectionService
{
    /**
     * In-app browser detection patterns.
     *
     * @var array<string, array<string>>
     */
    protected array $inAppPatterns = [
        'instagram' => ['Instagram'],
        'facebook' => ['FBAN', 'FBAV', 'FB_IAB', 'FBIOS', 'FBSS'],
        'tiktok' => ['BytedanceWebview', 'musical_ly', 'TikTok'],
        'twitter' => ['Twitter'],
        'linkedin' => ['LinkedInApp'],
        'snapchat' => ['Snapchat'],
        'threads' => ['Barcelona'], // Meta's internal codename
        'pinterest' => ['Pinterest'],
        'reddit' => ['Reddit'],
        'wechat' => ['MicroMessenger'],
        'line' => ['Line/'],
        'telegram' => ['TelegramBot', 'Telegram'],
        'discord' => ['Discord'],
        'whatsapp' => ['WhatsApp'],
    ];

    /**
     * Platforms with strict content policies.
     *
     * @var array<string>
     */
    protected array $strictPlatforms = [
        'instagram',
        'facebook',
        'threads',
        'tiktok',
        'twitter',
        'snapchat',
        'linkedin',
    ];

    /**
     * Meta-owned platforms.
     *
     * @var array<string>
     */
    protected array $metaPlatforms = [
        'instagram',
        'facebook',
        'threads',
    ];

    /**
     * Platform display names.
     *
     * @var array<string, string>
     */
    protected array $displayNames = [
        'instagram' => 'Instagram',
        'facebook' => 'Facebook',
        'tiktok' => 'TikTok',
        'twitter' => 'X (Twitter)',
        'linkedin' => 'LinkedIn',
        'snapchat' => 'Snapchat',
        'threads' => 'Threads',
        'pinterest' => 'Pinterest',
        'reddit' => 'Reddit',
        'wechat' => 'WeChat',
        'line' => 'LINE',
        'telegram' => 'Telegram',
        'discord' => 'Discord',
        'whatsapp' => 'WhatsApp',
    ];

    /**
     * Parse full device info from User-Agent.
     *
     * @return array{device_type: string, os_name: ?string, browser_name: ?string, in_app_browser: ?string, is_in_app: bool}
     */
    public function parse(?string $userAgent): array
    {
        $userAgent ??= '';

        return [
            'device_type' => $this->detectDeviceType($userAgent),
            'os_name' => $this->detectOS($userAgent),
            'browser_name' => $this->detectBrowser($userAgent),
            'in_app_browser' => $this->detectInAppBrowser($userAgent),
            'is_in_app' => $this->isInAppBrowser($userAgent),
        ];
    }

    // -------------------------------------------------------------------------
    // In-App Browser Detection
    // -------------------------------------------------------------------------

    /**
     * Detect which in-app browser (if any).
     */
    public function detectInAppBrowser(?string $userAgent): ?string
    {
        if (empty($userAgent)) {
            return null;
        }

        foreach ($this->inAppPatterns as $platform => $patterns) {
            foreach ($patterns as $pattern) {
                if (stripos($userAgent, $pattern) !== false) {
                    return $platform;
                }
            }
        }

        // Generic WebView detection
        if ($this->isGenericWebView($userAgent)) {
            return 'webview';
        }

        return null;
    }

    /**
     * Check if ANY in-app browser.
     */
    public function isInAppBrowser(?string $userAgent): bool
    {
        return $this->detectInAppBrowser($userAgent) !== null;
    }

    /**
     * Check for generic WebView markers.
     */
    protected function isGenericWebView(?string $userAgent): bool
    {
        if (empty($userAgent)) {
            return false;
        }

        // Android WebView marker
        if (preg_match('/\bwv\b/', $userAgent)) {
            return true;
        }

        // iOS WebView markers
        if (stripos($userAgent, 'AppleWebKit') !== false
            && stripos($userAgent, 'Safari') === false) {
            return true;
        }

        return false;
    }

    // -------------------------------------------------------------------------
    // Platform-Specific Checks
    // -------------------------------------------------------------------------

    public function isInstagram(?string $userAgent): bool
    {
        return $this->detectInAppBrowser($userAgent) === 'instagram';
    }

    public function isFacebook(?string $userAgent): bool
    {
        return $this->detectInAppBrowser($userAgent) === 'facebook';
    }

    public function isTikTok(?string $userAgent): bool
    {
        return $this->detectInAppBrowser($userAgent) === 'tiktok';
    }

    public function isTwitter(?string $userAgent): bool
    {
        return $this->detectInAppBrowser($userAgent) === 'twitter';
    }

    public function isSnapchat(?string $userAgent): bool
    {
        return $this->detectInAppBrowser($userAgent) === 'snapchat';
    }

    public function isLinkedIn(?string $userAgent): bool
    {
        return $this->detectInAppBrowser($userAgent) === 'linkedin';
    }

    public function isThreads(?string $userAgent): bool
    {
        return $this->detectInAppBrowser($userAgent) === 'threads';
    }

    public function isPinterest(?string $userAgent): bool
    {
        return $this->detectInAppBrowser($userAgent) === 'pinterest';
    }

    public function isReddit(?string $userAgent): bool
    {
        return $this->detectInAppBrowser($userAgent) === 'reddit';
    }

    // -------------------------------------------------------------------------
    // Grouped Platform Checks
    // -------------------------------------------------------------------------

    /**
     * Check if from a strict content platform.
     *
     * These platforms actively enforce content policies and may
     * deplatform users who link to adult/restricted content.
     */
    public function isStrictContentPlatform(?string $userAgent): bool
    {
        $platform = $this->detectInAppBrowser($userAgent);

        return $platform !== null && in_array($platform, $this->strictPlatforms, true);
    }

    /**
     * Check if from any Meta-owned platform.
     */
    public function isMetaPlatform(?string $userAgent): bool
    {
        $platform = $this->detectInAppBrowser($userAgent);

        return $platform !== null && in_array($platform, $this->metaPlatforms, true);
    }

    /**
     * Get human-readable platform name.
     */
    public function getPlatformDisplayName(?string $userAgent): ?string
    {
        $platform = $this->detectInAppBrowser($userAgent);

        if ($platform === null) {
            return null;
        }

        return $this->displayNames[$platform] ?? ucfirst($platform);
    }

    // -------------------------------------------------------------------------
    // Device Detection
    // -------------------------------------------------------------------------

    /**
     * Detect device type: mobile, tablet, or desktop.
     */
    public function detectDeviceType(?string $userAgent): string
    {
        if (empty($userAgent)) {
            return 'desktop';
        }

        // Tablets first (before mobile check catches them)
        if (preg_match('/iPad|Android(?!.*Mobile)|Tablet/i', $userAgent)) {
            return 'tablet';
        }

        // Mobile devices
        if (preg_match('/Mobile|iPhone|iPod|Android|webOS|BlackBerry|IEMobile|Opera Mini/i', $userAgent)) {
            return 'mobile';
        }

        return 'desktop';
    }

    /**
     * Detect operating system.
     */
    public function detectOS(?string $userAgent): ?string
    {
        if (empty($userAgent)) {
            return null;
        }

        $patterns = [
            'iOS' => '/iPhone|iPad|iPod/',
            'Android' => '/Android/',
            'Windows' => '/Windows NT/',
            'macOS' => '/Macintosh|Mac OS X/',
            'Linux' => '/Linux/',
            'ChromeOS' => '/CrOS/',
        ];

        foreach ($patterns as $os => $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return $os;
            }
        }

        return null;
    }

    /**
     * Detect browser (when not in-app).
     */
    public function detectBrowser(?string $userAgent): ?string
    {
        if (empty($userAgent)) {
            return null;
        }

        // In-app browsers often don't have standard browser identification
        if ($this->isInAppBrowser($userAgent)) {
            return null;
        }

        // Order matters - check specific before generic
        $patterns = [
            'Edge' => '/Edg\//',
            'Opera' => '/OPR\/|Opera/',
            'Chrome' => '/Chrome\//',
            'Firefox' => '/Firefox\//',
            'Safari' => '/Safari\//',
            'IE' => '/MSIE|Trident/',
        ];

        foreach ($patterns as $browser => $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return $browser;
            }
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // Bot Detection
    // -------------------------------------------------------------------------

    /**
     * Check if User-Agent appears to be a bot/crawler.
     */
    public function isBot(?string $userAgent): bool
    {
        if (empty($userAgent)) {
            return false;
        }

        $botPatterns = [
            'bot', 'crawl', 'spider', 'slurp', 'mediapartners',
            'Googlebot', 'Bingbot', 'Baiduspider', 'YandexBot',
            'DuckDuckBot', 'facebookexternalhit', 'Twitterbot',
            'LinkedInBot', 'WhatsApp', 'TelegramBot', 'Discordbot',
            'Applebot', 'AhrefsBot', 'SemrushBot', 'MJ12bot',
        ];

        foreach ($botPatterns as $pattern) {
            if (stripos($userAgent, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get list of strict content platforms.
     *
     * @return array<string>
     */
    public function getStrictPlatforms(): array
    {
        return $this->strictPlatforms;
    }

    /**
     * Get list of Meta platforms.
     *
     * @return array<string>
     */
    public function getMetaPlatforms(): array
    {
        return $this->metaPlatforms;
    }
}
