# Core Package Release Plan

**Package:** `host-uk/core` (GitHub: host-uk/core)
**Namespace:** `Core\` (not `Snide\` - that's *barf*)
**Usage:** `<core:button>`, `Core\Front\Components\Button::make()`

---

## Value Proposition

Core provides:
1. **Thin Flux Wrappers** - `<core:*>` components that pass through to `<flux:*>` with 100% parity
2. **HLCRF Layout System** - Compositor pattern for page layouts (Header, Left, Content, Right, Footer)
3. **FontAwesome Pro Integration** - Custom icon system with brand/jelly auto-detection
4. **PHP Builders** - Programmatic UI composition (`Button::make()->primary()`)
5. **Graceful Degradation** - Falls back to free versions of Flux/FontAwesome

---

## Detection Strategy

### Flux Pro vs Free

```php
use Composer\InstalledVersions;

class Core
{
    public static function hasFluxPro(): bool
    {
        return InstalledVersions::isInstalled('livewire/flux-pro');
    }

    public static function proComponents(): array
    {
        return [
            'calendar', 'date-picker', 'time-picker',
            'editor', 'composer',
            'chart', 'kanban',
            'command', 'context',
            'autocomplete', 'pillbox', 'slider',
            'file-upload',
        ];
    }
}
```

### FontAwesome Pro vs Free

```php
class Core
{
    public static function hasFontAwesomePro(): bool
    {
        // Check for FA Pro kit or CDN link in config
        return config('core.fontawesome.pro', false);
    }

    public static function faStyles(): array
    {
        // Pro: solid, regular, light, thin, duotone, brands, sharp, jelly
        // Free: solid, regular, brands
        return self::hasFontAwesomePro()
            ? ['solid', 'regular', 'light', 'thin', 'duotone', 'brands', 'sharp', 'jelly']
            : ['solid', 'regular', 'brands'];
    }
}
```

---

## Graceful Degradation

### Pro-Only Flux Components

When Flux Pro isn't installed, `<core:calendar>` etc. should:

**Option A: Helpful Error** (recommended for development)
```blade
{{-- calendar.blade.php --}}
@if(Core::hasFluxPro())
    <flux:calendar {{ $attributes }} />
@else
    <div class="p-4 border border-amber-300 bg-amber-50 rounded text-amber-800 text-sm">
        <strong>Calendar requires Flux Pro.</strong>
        <a href="https://fluxui.dev" class="underline">Learn more</a>
    </div>
@endif
```

**Option B: Silent Fallback** (for production)
```blade
{{-- calendar.blade.php --}}
@if(Core::hasFluxPro())
    <flux:calendar {{ $attributes }} />
@else
    {{-- Graceful degradation: render nothing or a basic HTML input --}}
    <input type="date" {{ $attributes }} />
@endif
```

### FontAwesome Style Fallback

```php
// In icon.blade.php
$availableStyles = Core::faStyles();

// Map pro-only styles to free equivalents
$styleFallback = [
    'light' => 'regular',    // FA Light → FA Regular
    'thin' => 'regular',     // FA Thin → FA Regular
    'duotone' => 'solid',    // FA Duotone → FA Solid
    'sharp' => 'solid',      // FA Sharp → FA Solid
    'jelly' => 'solid',      // Host UK Jelly → FA Solid
];

if (!in_array($iconStyle, $availableStyles)) {
    $iconStyle = $styleFallback[$iconStyle] ?? 'fa-solid';
}
```

---

## Package Structure (Root Level)

```
host-uk/core/
├── composer.json
├── LICENSE
├── README.md
├── Core/
│   ├── Boot.php                         # ServiceProvider
│   ├── Core.php                         # Detection helpers + facade
│   ├── Front/
│   │   ├── Boot.php
│   │   └── Components/
│   │       ├── CoreTagCompiler.php      # <core:*> syntax
│   │       ├── View/
│   │       │   └── Blade/               # 100+ components
│   │       │       ├── button.blade.php
│   │       │       ├── icon.blade.php
│   │       │       ├── layout.blade.php
│   │       │       └── layout/
│   │       ├── Button.php               # PHP Builder
│   │       ├── Card.php
│   │       ├── Heading.php
│   │       ├── Layout.php               # HLCRF compositor
│   │       ├── NavList.php
│   │       └── Text.php
│   └── config.php                       # Package config
├── tests/
│   └── Feature/
│       └── CoreComponentsTest.php
└── .github/
    └── workflows/
        └── tests.yml
```

**Note:** This mirrors Host Hub's current `app/Core/` structure exactly, just at root level. Minimal refactoring needed.

---

## composer.json

```json
{
    "name": "host-uk/core",
    "description": "Core UI component library for Laravel - Flux Pro/Free compatible",
    "keywords": ["laravel", "livewire", "flux", "components", "ui"],
    "license": "MIT",
    "authors": [
        {
            "name": "Snider",
            "homepage": "https://host.uk.com"
        }
    ],
    "require": {
        "php": "^8.2",
        "laravel/framework": "^11.0|^12.0",
        "livewire/livewire": "^3.0",
        "livewire/flux": "^2.0"
    },
    "suggest": {
        "livewire/flux-pro": "Required for Pro components (calendar, editor, chart, etc.)"
    },
    "autoload": {
        "psr-4": {
            "Core\\": "src/Core/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "Core\\CoreServiceProvider"
            ]
        }
    }
}
```

---

## Configuration

```php
// config/core.php
return [
    /*
    |--------------------------------------------------------------------------
    | FontAwesome Configuration
    |--------------------------------------------------------------------------
    */
    'fontawesome' => [
        'pro' => env('FONTAWESOME_PRO', false),
        'kit' => env('FONTAWESOME_KIT'),  // e.g., 'abc123def456'
    ],

    /*
    |--------------------------------------------------------------------------
    | Fallback Behaviour
    |--------------------------------------------------------------------------
    | How to handle Pro components when Pro isn't installed.
    | Options: 'error', 'fallback', 'silent'
    */
    'pro_fallback' => env('CORE_PRO_FALLBACK', 'error'),
];
```

---

## Migration Path

### Step 1: Extract Core (Host Hub)
Move `app/Core/Front/Components/` to standalone package, update namespace `Core\` → `Core\`

### Step 2: Install Package Back
```bash
composer require host-uk/core
```

### Step 3: Host Hub Uses Package
Replace `app/Core/Front/Components/` with import from package. Keep Host-specific stuff in `app/Core/`.

---

## What Stays in Host Hub

These are too app-specific for the package:
- `Core/Cdn/` - BunnyCDN integration
- `Core/Config/` - Multi-tenant config system
- `Core/Mail/` - EmailShield
- `Core/Seo/` - Schema, OG images
- `Core/Headers/` - Security headers (maybe extract later)
- `Core/Media/` - ImageOptimizer (maybe extract later)

---

## What Goes in Package

Universal value:
- `Core/Front/Components/` - All 100+ Blade components
- `Core/Front/Components/*.php` - PHP Builders
- `CoreTagCompiler.php` - `<core:*>` syntax

---

## Questions to Resolve

1. **Package name:** `host-uk/core`?
2. **FontAwesome:** Detect Kit from asset URL, or require config?
3. **Fallback mode:** Default to 'error' (dev-friendly) or 'fallback' (prod-safe)?
4. **Jelly icons:** Include your custom FA style in package, or keep Host UK specific?

---

## Implementation Progress

### Done ✅

1. **Detection helpers** - `app/Core/Core.php`
   - `Core::hasFluxPro()` - Uses Composer InstalledVersions
   - `Core::hasFontAwesomePro()` - Uses config
   - `Core::requiresFluxPro($component)` - Checks if component needs Pro
   - `Core::fontAwesomeStyles()` - Returns available styles
   - `Core::fontAwesomeFallback($style)` - Maps Pro→Free styles

2. **Config file** - `app/Core/config.php`
   - `fontawesome.pro` - Enable FA Pro styles
   - `fontawesome.kit` - FA Kit ID
   - `pro_fallback` - How to handle Pro components (error/fallback/silent)

3. **Icon fallback** - `app/Core/Front/Components/View/Blade/icon.blade.php`
   - Auto-detects FA Pro availability
   - Falls back: jelly→solid, light→regular, thin→regular, duotone→solid

4. **Test coverage** - 49 tests, 79 assertions
   - Detection helper tests
   - Icon fallback tests (Pro/Free scenarios)
   - Full Flux parity tests

### TODO

1. Create pro-component wrappers with fallback (calendar, editor, chart, etc.)
2. Test with Flux Free only (remove flux-pro temporarily)
3. Extract to separate repo
4. Update namespace `Core\` → `Core\`
5. Create composer.json for package
6. Publish to Packagist
