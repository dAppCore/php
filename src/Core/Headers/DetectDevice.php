<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Headers;

/**
 * Device detection service for parsing User-Agent strings.
 *
 * Extracts device type, operating system, browser, and in-app browser context.
 * Used by Analytics Center, BioHost, and other services for consistent detection.
 */
class DetectDevice
{
    /**
     * In-app browser identifiers.
     *
     * Maps platform name to UA pattern(s).
     */
    protected const IN_APP_BROWSERS = [
        'instagram' => '/Instagram/i',
        'facebook' => '/FBAN|FBAV|FB_IAB|FBIOS|FBSS/i',
        'tiktok' => '/BytedanceWebview|musical_ly|TikTok/i',
        'twitter' => '/Twitter/i',
        'linkedin' => '/LinkedInApp/i',
        'snapchat' => '/Snapchat/i',
        'pinterest' => '/Pinterest/i',
        'reddit' => '/Reddit/i',
        'threads' => '/Barcelona/i', // Meta's internal codename for Threads
        'wechat' => '/MicroMessenger/i',
        'line' => '/\bLine\b/i',
        'telegram' => '/\bTelegram\b(?!Bot)/i', // Telegram app, not TelegramBot
        'discord' => '/\bDiscord\b(?!Bot)/i', // Discord app, not DiscordBot
        'whatsapp' => '/\bWhatsApp\b(?!\/)/i', // WhatsApp app, not bot
    ];

    /**
     * Generic WebView patterns (catch-all for unknown in-app browsers).
     */
    protected const WEBVIEW_PATTERNS = [
        '/\bwv\b/i', // Android WebView
        '/WebView/i',
        '/\(.*;\s*wv\s*\)/i', // Android WebView in UA
        '/\bGSA\b/i', // Google Search App
    ];

    /**
     * Parse all device info from a User-Agent string.
     *
     * @return array{device_type: string, os_name: ?string, browser_name: ?string, in_app_browser: ?string, is_in_app: bool}
     */
    public function parse(?string $userAgent): array
    {
        $inAppBrowser = $this->detectInAppBrowser($userAgent);

        return [
            'device_type' => $this->detectDeviceType($userAgent),
            'os_name' => $this->detectOs($userAgent),
            'browser_name' => $this->detectBrowser($userAgent),
            'in_app_browser' => $inAppBrowser,
            'is_in_app' => $inAppBrowser !== null,
        ];
    }

    /**
     * Detect device type: desktop, mobile, or tablet.
     */
    public function detectDeviceType(?string $userAgent): string
    {
        if (! $userAgent) {
            return 'desktop';
        }

        $ua = strtolower($userAgent);

        // Check for tablets first (before mobile, since some tablets match mobile patterns)
        if (preg_match('/ipad|tablet|playbook|silk/i', $ua)) {
            return 'tablet';
        }

        // Check for mobile devices
        if (preg_match('/mobile|android|iphone|ipod|windows phone|blackberry|bb10|opera mini|opera mobi/i', $ua)) {
            // Android tablets don't have 'Mobile' in UA, but phones do
            if (preg_match('/android/i', $ua) && ! preg_match('/mobile/i', $ua)) {
                return 'tablet';
            }

            return 'mobile';
        }

        return 'desktop';
    }

    /**
     * Detect operating system from User-Agent.
     */
    public function detectOs(?string $userAgent): ?string
    {
        if (! $userAgent) {
            return null;
        }

        // Order matters - check specific patterns before generic ones
        $patterns = [
            'Windows 11' => '/Windows NT 10.*Win64/i', // Win11 shares NT version with Win10
            'Windows 10' => '/Windows NT 10/i',
            'Windows 8.1' => '/Windows NT 6\.3/i',
            'Windows 8' => '/Windows NT 6\.2/i',
            'Windows 7' => '/Windows NT 6\.1/i',
            'Windows Vista' => '/Windows NT 6\.0/i',
            'Windows XP' => '/Windows NT 5\.[12]/i',
            'iOS' => '/iPhone|iPad|iPod/i',
            'macOS' => '/Mac OS X|Macintosh/i',
            'Android' => '/Android/i',
            'Chrome OS' => '/CrOS/i',
            'Linux' => '/Linux/i',
            'FreeBSD' => '/FreeBSD/i',
        ];

        foreach ($patterns as $os => $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return $os;
            }
        }

        return null;
    }

    /**
     * Detect browser from User-Agent.
     */
    public function detectBrowser(?string $userAgent): ?string
    {
        if (! $userAgent) {
            return null;
        }

        // Order matters - check specific browsers before generic ones
        // E.g., Edge and Chrome both have "Chrome" in UA, but Edge has "Edg"
        $patterns = [
            'Edge' => '/Edg\//i',
            'Opera' => '/OPR\/|Opera/i',
            'Brave' => '/Brave/i',
            'Vivaldi' => '/Vivaldi/i',
            'Samsung Browser' => '/SamsungBrowser/i',
            'UC Browser' => '/UCBrowser/i',
            'Yandex' => '/YaBrowser/i',
            'DuckDuckGo' => '/DuckDuckGo/i',
            'Chrome' => '/Chrome/i',
            'Firefox' => '/Firefox/i',
            'Safari' => '/Safari/i',
            'IE' => '/MSIE|Trident/i',
        ];

        foreach ($patterns as $browser => $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return $browser;
            }
        }

        return null;
    }

    /**
     * Check if the User-Agent appears to be a bot.
     */
    public function isBot(?string $userAgent): bool
    {
        if (! $userAgent) {
            return false;
        }

        $botPatterns = [
            '/bot/i',
            '/spider/i',
            '/crawl/i',
            '/slurp/i',
            '/mediapartners/i',
            '/facebookexternalhit/i',
            '/twitterbot/i',
            '/linkedinbot/i',
            '/whatsapp\//i', // WhatsApp link preview bot (has slash after)
            '/telegrambot/i',
            '/discordbot/i',
            '/googlebot/i',
            '/bingbot/i',
            '/yandexbot/i',
            '/duckduckbot/i',
            '/baiduspider/i',
            '/semrushbot/i',
            '/ahrefsbot/i',
            '/mj12bot/i',
            '/dotbot/i',
            '/rogerbot/i',
            '/curl/i',
            '/wget/i',
            '/python-requests/i',
            '/go-http-client/i',
            '/headlesschrome/i',
            '/phantomjs/i',
            '/lighthouse/i',
            '/pingdom/i',
            '/uptimerobot/i',
        ];

        foreach ($botPatterns as $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return true;
            }
        }

        return false;
    }

    // =========================================================================
    // In-App Browser Detection
    // =========================================================================

    /**
     * Detect which in-app browser is being used, if any.
     *
     * @return string|null Platform name (lowercase) or null if not in-app
     */
    public function detectInAppBrowser(?string $userAgent): ?string
    {
        if (! $userAgent) {
            return null;
        }

        // Check specific platforms first
        foreach (self::IN_APP_BROWSERS as $platform => $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return $platform;
            }
        }

        // Check for generic WebView (unknown in-app browser)
        foreach (self::WEBVIEW_PATTERNS as $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return 'webview';
            }
        }

        return null;
    }

    /**
     * Check if browsing from any in-app browser.
     */
    public function isInAppBrowser(?string $userAgent): bool
    {
        return $this->detectInAppBrowser($userAgent) !== null;
    }

    /**
     * Check if browsing from Instagram's in-app browser.
     */
    public function isInstagram(?string $userAgent): bool
    {
        return $this->detectInAppBrowser($userAgent) === 'instagram';
    }

    /**
     * Check if browsing from Facebook's in-app browser.
     */
    public function isFacebook(?string $userAgent): bool
    {
        return $this->detectInAppBrowser($userAgent) === 'facebook';
    }

    /**
     * Check if browsing from TikTok's in-app browser.
     */
    public function isTikTok(?string $userAgent): bool
    {
        return $this->detectInAppBrowser($userAgent) === 'tiktok';
    }

    /**
     * Check if browsing from Twitter/X's in-app browser.
     */
    public function isTwitter(?string $userAgent): bool
    {
        return $this->detectInAppBrowser($userAgent) === 'twitter';
    }

    /**
     * Check if browsing from LinkedIn's in-app browser.
     */
    public function isLinkedIn(?string $userAgent): bool
    {
        return $this->detectInAppBrowser($userAgent) === 'linkedin';
    }

    /**
     * Check if browsing from Snapchat's in-app browser.
     */
    public function isSnapchat(?string $userAgent): bool
    {
        return $this->detectInAppBrowser($userAgent) === 'snapchat';
    }

    /**
     * Check if browsing from Pinterest's in-app browser.
     */
    public function isPinterest(?string $userAgent): bool
    {
        return $this->detectInAppBrowser($userAgent) === 'pinterest';
    }

    /**
     * Check if browsing from Reddit's in-app browser.
     */
    public function isReddit(?string $userAgent): bool
    {
        return $this->detectInAppBrowser($userAgent) === 'reddit';
    }

    /**
     * Check if browsing from Threads' in-app browser.
     */
    public function isThreads(?string $userAgent): bool
    {
        return $this->detectInAppBrowser($userAgent) === 'threads';
    }

    /**
     * Check if browsing from any Meta platform (Instagram, Facebook, Threads).
     *
     * Useful for applying consistent 18+ warning policies across Meta apps.
     */
    public function isMetaPlatform(?string $userAgent): bool
    {
        $platform = $this->detectInAppBrowser($userAgent);

        return in_array($platform, ['instagram', 'facebook', 'threads'], true);
    }

    /**
     * Check if the platform is known to be strict about adult content.
     *
     * These platforms may deplatform users who link to adult content without warnings.
     * Returns true for: Instagram, Facebook, Threads, TikTok, Snapchat, LinkedIn.
     */
    public function isStrictContentPlatform(?string $userAgent): bool
    {
        $platform = $this->detectInAppBrowser($userAgent);

        return in_array($platform, [
            'instagram',
            'facebook',
            'threads',
            'tiktok',
            'twitter',
            'snapchat',
            'linkedin',
        ], true);
    }

    /**
     * Get a human-readable platform name for display.
     */
    public function getPlatformDisplayName(?string $userAgent): ?string
    {
        $platform = $this->detectInAppBrowser($userAgent);

        if (! $platform) {
            return null;
        }

        return match ($platform) {
            'instagram' => 'Instagram',
            'facebook' => 'Facebook',
            'tiktok' => 'TikTok',
            'twitter' => 'X (Twitter)',
            'linkedin' => 'LinkedIn',
            'snapchat' => 'Snapchat',
            'pinterest' => 'Pinterest',
            'reddit' => 'Reddit',
            'threads' => 'Threads',
            'wechat' => 'WeChat',
            'line' => 'LINE',
            'telegram' => 'Telegram',
            'discord' => 'Discord',
            'whatsapp' => 'WhatsApp',
            'webview' => 'In-App Browser',
            default => ucfirst($platform),
        };
    }
}
