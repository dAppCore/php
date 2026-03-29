# Lang/Coverage/ — Translation Coverage Analysis

## Files

| File | Purpose |
|------|---------|
| `TranslationCoverage` | Scans PHP, Blade, and JS/Vue files for translation key usage (`__()`, `trans()`, `@lang`, etc.) and compares against translation files. Reports missing keys (used but undefined) and unused keys (defined but unused). |
| `TranslationCoverageReport` | DTO containing analysis results — missing keys, unused keys, coverage statistics per locale, and usage locations. Implements `Arrayable`. |

Used by the `lang:coverage` Artisan command for translation quality assurance.
