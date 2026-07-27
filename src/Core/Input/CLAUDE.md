# Core\Input

Pre-boot input sanitisation. Strips dangerous control characters from `$_GET` and `$_POST` before Laravel even creates the Request object.

## Key Classes

| Class | Purpose |
|-------|---------|
| `Input` | Static `capture()` method -- sanitises superglobals then delegates to `Request::capture()` |
| `Sanitiser` | Configurable filter pipeline: Unicode NFC normalisation, control char stripping, HTML filtering, presets, max length, transformation hooks |

## Sanitiser Pipeline

Execution order per string value:

1. Before hooks (global, then field-specific)
2. Unicode NFC normalisation (via `intl` extension)
3. Control character stripping (`FILTER_UNSAFE_RAW` + `FILTER_FLAG_STRIP_LOW`)
4. HTML tag filtering (strip_tags with allowed tags)
5. Preset application (email, url, phone, alpha, alphanumeric, numeric, slug)
6. Additional schema-defined `filter_var` filters
7. Max length enforcement (`mb_substr`)
8. After hooks (global, then field-specific)
9. Audit logging (if enabled and value changed)

## Public API

```php
// Immutable builder pattern (returns cloned instance)
$s = (new Sanitiser)
    ->richText()                          // allow safe HTML tags
    ->maxLength(1000)                     // truncate to 1000 chars
    ->email('email_field')                // apply email preset to specific field
    ->slug('url_slug')                    // apply slug preset
    ->beforeFilter(fn($v, $f) => trim($v))
    ->transformField('username', fn($v) => strtolower($v));

$clean = $s->filter(['email_field' => $raw, 'url_slug' => $raw2]);
```

## Conventions

- **Sanitiser sanitises, Laravel validates.** This is explicitly called out in the class docblock.
- Immutable: all `with*` / fluent methods return `clone $this`.
- Presets are static and extensible via `Sanitiser::registerPreset()`.
- The `*` wildcard key in schema applies to all fields as a default.
- Field-specific schema merges over global (`*`) schema.

## Tests

Pest tests at `Tests/Unit/InputFilteringTest.php` cover: clean passthrough, control char stripping, full Unicode preservation (CJK, Arabic, Russian, emojis), and edge cases.
