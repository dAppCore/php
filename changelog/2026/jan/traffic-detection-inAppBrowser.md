# In-App Browser Detection

Detects when users visit from social media in-app browsers (Instagram, TikTok, etc.) rather than standard browsers.

## Why this exists

Creators sharing links on social platforms need to know when traffic comes from in-app browsers because:

- **Content policies differ** - Some platforms deplatform users who link to adult content without warnings
- **User experience varies** - In-app browsers have limitations (no extensions, different cookie handling)
- **Traffic routing** - Creators may want to redirect certain platform traffic or show platform-specific messaging

## Location

```
app/Services/Shared/DeviceDetectionService.php
```

## Basic usage

```php
use App\Services\Shared\DeviceDetectionService;

$dd = app(DeviceDetectionService::class);
$ua = request()->userAgent();

// Check for specific platforms
$dd->isInstagram($ua)     // true if Instagram in-app browser
$dd->isFacebook($ua)      // true if Facebook in-app browser
$dd->isTikTok($ua)        // true if TikTok in-app browser
$dd->isTwitter($ua)       // true if Twitter/X in-app browser
$dd->isSnapchat($ua)      // true if Snapchat in-app browser
$dd->isLinkedIn($ua)      // true if LinkedIn in-app browser
$dd->isThreads($ua)       // true if Threads in-app browser
$dd->isPinterest($ua)     // true if Pinterest in-app browser
$dd->isReddit($ua)        // true if Reddit in-app browser

// General checks
$dd->isInAppBrowser($ua)  // true if ANY in-app browser
$dd->isMetaPlatform($ua)  // true if Instagram, Facebook, or Threads
```

## Grouped platform checks

### Strict content platforms

Platforms known to enforce content policies that may result in account action:

```php
$dd->isStrictContentPlatform($ua)
// Returns true for: Instagram, Facebook, Threads, TikTok, Twitter, Snapchat, LinkedIn
```

### Meta platforms

All Meta-owned apps (useful for consistent policy application):

```php
$dd->isMetaPlatform($ua)
// Returns true for: Instagram, Facebook, Threads
```

## Example: BioHost 18+ warning

Show a content warning when adult content is accessed from strict platforms:

```php
// In PublicBioPageController or Livewire component
$deviceDetection = app(DeviceDetectionService::class);

$showAdultWarning = $biolink->is_adult_content
    && $deviceDetection->isStrictContentPlatform(request()->userAgent());

// Or target a specific platform
$showInstagramWarning = $biolink->is_adult_content
    && $deviceDetection->isInstagram(request()->userAgent());
```

## Full device info

The `parse()` method returns all detection data at once:

```php
$dd->parse($ua);

// Returns:
[
    'device_type' => 'mobile',
    'os_name' => 'iOS',
    'browser_name' => null,       // In-app browsers often lack browser identification
    'in_app_browser' => 'instagram',
    'is_in_app' => true,
]
```

## Display names

Get human-readable platform names for UI display:

```php
$dd->getPlatformDisplayName($ua);

// Returns: "Instagram", "TikTok", "X (Twitter)", "LinkedIn", etc.
// Returns null if not an in-app browser
```

## Supported platforms

| Platform | Method | In strict list |
|----------|--------|----------------|
| Instagram | `isInstagram()` | Yes |
| Facebook | `isFacebook()` | Yes |
| Threads | `isThreads()` | Yes |
| TikTok | `isTikTok()` | Yes |
| Twitter/X | `isTwitter()` | Yes |
| Snapchat | `isSnapchat()` | Yes |
| LinkedIn | `isLinkedIn()` | Yes |
| Pinterest | `isPinterest()` | No |
| Reddit | `isReddit()` | No |
| WeChat | via `detectInAppBrowser()` | No |
| LINE | via `detectInAppBrowser()` | No |
| Telegram | via `detectInAppBrowser()` | No |
| Discord | via `detectInAppBrowser()` | No |
| WhatsApp | via `detectInAppBrowser()` | No |
| Generic WebView | `isInAppBrowser()` | No |

## How detection works

Each platform adds identifiable strings to their in-app browser User-Agent:

```
Instagram: "Instagram" in UA
Facebook:  "FBAN", "FBAV", "FB_IAB", "FBIOS", or "FBSS"
TikTok:    "BytedanceWebview", "musical_ly", or "TikTok"
Twitter:   "Twitter" in UA
LinkedIn:  "LinkedInApp"
Snapchat:  "Snapchat"
Threads:   "Barcelona" (Meta's internal codename)
```

Generic WebView detection catches unknown in-app browsers via patterns like `wv` (Android WebView marker).

## Related services

This service is part of the shared services extracted for use across the platform:

- `DeviceDetectionService` - Device type, OS, browser, bot detection, in-app browser detection
- `GeoIpService` - IP geolocation from CDN headers or MaxMind
- `PrivacyHelper` - IP anonymisation and hashing
- `UtmHelper` - UTM parameter extraction

See also: `doc/dev-feat-docs/traffic-detections/` for other detection features.
