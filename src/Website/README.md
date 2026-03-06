# Website Module

Marketing websites outside the Mod structure.

## Structure

```
app/Website/
├── Boot.php              # ServiceProvider (registers DomainResolver + websites)
├── DomainResolver.php    # Domain → website mapping
├── README.md
└── HostUk/               # host.uk.com / host.test
    ├── Boot.php          # Website ServiceProvider
    ├── Routes/web.php    # All marketing routes (/, /login, /pricing, etc.)
    ├── View/
    │   ├── Blade/        # Blade templates
    │   └── Modal/        # Livewire components
    ├── Mail/             # Mailable classes
    ├── Lang/en_GB/       # Translations (pages.php)
    └── Tests/Feature/    # Feature tests
```

## Domain Resolution

The `DomainResolver` maps incoming domains to website directories:

```php
$resolver = app(DomainResolver::class);

$resolver->resolve('host.uk.com');     // → 'HostUk'
$resolver->resolve('www.host.uk.com'); // → 'HostUk'
$resolver->resolve('host.test');       // → 'HostUk'

$resolver->isWebsite('host.uk.com');   // → true
$resolver->isWebsite('random.com');    // → false
```

## Namespaces

- **Classes:** `Website\Host\...`
- **Views:** `hostuk::` (e.g., `hostuk::home`)
- **Translations:** `pages::` (backward compatible)
- **Livewire:** `pages.*` (backward compatible)

## Adding a New Website

1. Add pattern to `DomainResolver::$websites`:
   ```php
   '/^newsite\.(com|test)$/' => 'NewSite',
   ```

2. Create directory structure:
   ```
   app/Website/NewSite/
   ├── Boot.php
   ├── Routes/web.php
   ├── View/
   └── Lang/en_GB/
   ```

3. Register in `Website\Boot::register()`:
   ```php
   $this->app->register(NewSite\Boot::class);
   ```

## Routes

All marketing website routes are defined in `HostUk/Routes/web.php`:
- Landing pages (`/`, `/pricing`, `/about`, `/contact`)
- Service pages (`/services/*`)
- Authentication (`/login`, `/register`, `/forgot-password`)
- Legal pages (`/terms`, `/privacy`, `/faq`)
- Developer docs (`/developers/*`)
- AI pages (`/ai/*`)
- Audience pages (`/for/*`)

## Migration from Mod/Pages

This module was migrated from `Mod/Pages/` to separate marketing content from business modules. The Livewire component names (`pages.*`) and translation namespace (`pages::`) were kept for backward compatibility.
