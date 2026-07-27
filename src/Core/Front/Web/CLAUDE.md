# Front/Web

Public-facing web frontage. Service provider that configures the `web` middleware group and registers `<web:xyz>` Blade tag syntax.

## Files

- **Boot.php** -- ServiceProvider that configures the `web` middleware stack (cookies, session, CSRF, security headers, domain resolution), registers the `web::` Blade namespace, fires `WebRoutesRegistering` lifecycle event, and aliases `livewire` as `admin` for Flux compatibility.
- **WebTagCompiler.php** -- Blade precompiler enabling `<web:xyz>` tag syntax (like `<flux:xyz>`). Compiles to anonymous components in the `web::` namespace.

## Middleware Stack

The `web` group includes: EncryptCookies, AddQueuedCookiesToResponse, StartSession, ResilientSession, ShareErrorsFromSession, ValidateCsrfToken, SubstituteBindings, SecurityHeaders, FindDomainRecord.

## Tag Syntax

```blade
<web:page title="Welcome">Content here</web:page>
<web:nav-item href="/about" icon="info">About</web:nav-item>
```

Resolves to anonymous components in `Web/Blade/components/` and `Components/View/Blade/web/`.
