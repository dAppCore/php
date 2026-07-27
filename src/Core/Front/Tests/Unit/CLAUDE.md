# Front/Tests/Unit

Unit tests for the Front module.

## Files

- **DeviceDetectionServiceTest.php** -- Tests for `DeviceDetectionService`. Covers in-app browser detection (Instagram, Facebook, TikTok, Twitter, LinkedIn, Snapchat, Threads, Pinterest, Reddit, WeChat, WhatsApp), standard browser exclusion, platform-specific methods, strict content platform identification, Meta platform detection, display names, device type/OS/browser detection, bot detection, and full parse output. Uses PHPUnit DataProvider for UA string coverage.

## Running

```bash
composer test -- --filter=DeviceDetectionServiceTest
```
