# Front/Services

Shared services for the Front module.

## Files

- **DeviceDetectionService.php** -- User-Agent parser for device type, OS, browser, in-app browser, and bot detection. Detects 14 social platform in-app browsers (Instagram, Facebook, TikTok, Twitter, LinkedIn, Snapchat, Threads, Pinterest, Reddit, WeChat, LINE, Telegram, Discord, WhatsApp) plus generic WebView. Identifies strict content platforms (Meta, TikTok, Twitter, Snapchat, LinkedIn) and Meta-owned platforms. Platform-specific methods: `isInstagram()`, `isFacebook()`, `isTikTok()`, etc. Full parse returns `{device_type, os_name, browser_name, in_app_browser, is_in_app}`.

## Usage

```php
$service = new DeviceDetectionService();
$info = $service->parse($request->userAgent());

if ($service->isStrictContentPlatform($ua)) {
    // Hide restricted content for platform compliance
}
```
