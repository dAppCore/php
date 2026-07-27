# Helpers/Rules/ — Custom Validation Rules

## Rules

| Rule | Purpose |
|------|---------|
| `HexRule` | Validates hexadecimal colour codes. Supports 3-digit (`#fff`) and 6-digit (`#ffffff`) formats. Hash symbol required. Constructor option `forceFull: true` rejects 3-digit codes. |

Usage:
```php
'colour' => ['required', new HexRule()]
'colour' => ['required', new HexRule(forceFull: true)]
```
