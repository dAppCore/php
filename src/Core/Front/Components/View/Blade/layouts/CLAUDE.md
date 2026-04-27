# Blade/layouts

Page layout templates registered under the `layouts::` namespace. Used by Livewire components via `->layout('layouts::app')`.

## Files

- **app.blade.php** -- Marketing/sales layout with particle animation. For landing pages, pricing, about, services.
- **content.blade.php** -- Blog posts, guides, legal pages. Centred prose layout.
- **focused.blade.php** -- Checkout, forms, onboarding. Minimal, distraction-free layout.
- **minimal.blade.php** -- Bare minimum layout with no navigation.
- **sidebar-left.blade.php** -- Help centre, FAQ, documentation. Left nav + content.
- **sidebar-right.blade.php** -- Long guides with TOC. Content + right sidebar.
- **workspace.blade.php** -- Authenticated SaaS workspace layout.

## Subdirectories

- **partials/** -- Shared layout fragments (base HTML shell, fonts, header, footer)

## Usage

```php
// In Livewire component
public function layout(): string
{
    return 'layouts::app';
}
```
