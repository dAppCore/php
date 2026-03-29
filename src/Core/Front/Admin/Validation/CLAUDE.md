# Front/Admin/Validation

Validation utilities for admin panel configuration.

## Files

- **IconValidator.php** -- Validates FontAwesome icon names used in admin menu items. Accepts shorthand (`home`), prefixed (`fa-home`), and full class (`fas fa-home`, `fa-solid fa-home`, `fab fa-github`) formats. Normalises all formats to base name. Contains built-in lists of ~200 solid icons and ~80 brand icons. Supports custom icons (`addCustomIcon()`), icon packs (`registerIconPack()`), and strict mode (config `core.admin_menu.strict_icon_validation`). Non-strict mode (default) allows unknown icons with optional warnings. Provides Levenshtein-based suggestions for misspelled icons (`getSuggestions()`).

## Configuration

- `core.admin_menu.strict_icon_validation` -- Reject unknown icons (default: false)
- `core.admin_menu.log_icon_warnings` -- Log warnings for unknown icons (default: true)
- `core.admin_menu.custom_icons` -- Array of additional valid icon names
