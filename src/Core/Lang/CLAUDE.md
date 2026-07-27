# Core\Lang

Internationalisation subsystem with ICU message formatting, translation memory with fuzzy matching, TMX import/export, and translation coverage analysis.

## Key Classes

| Class | Purpose |
|-------|---------|
| `LangServiceProvider` | Auto-discovered provider: registers ICU formatter, TM, coverage, fallback chain, missing key validation |
| `IcuMessageFormatter` | ICU MessageFormat support (plurals, select, number/date formatting) with intl fallback |
| `Boot` | Empty marker class -- all work done in LangServiceProvider |

### TranslationMemory/

| Class | Purpose |
|-------|---------|
| `TranslationMemory` | Facade service: store, get, suggest (fuzzy), import/export TMX |
| `FuzzyMatcher` | Multi-algorithm similarity: Levenshtein + token (Jaccard) + n-gram (Dice coefficient), configurable weights |
| `TranslationMemoryEntry` | Immutable value object with quality scores (0.0-1.0), usage counts, metadata |
| `JsonTranslationMemoryRepository` | File-backed repo: JSON files per locale pair, in-memory cache + dirty tracking |
| `TranslationMemoryRepository` (contract) | Interface for storage backends (JSON, database) |
| `TmxImporter` / `TmxExporter` | TMX 1.4b format import/export with locale normalisation and metadata preservation |

### Coverage/

| Class | Purpose |
|-------|---------|
| `TranslationCoverage` | Scans PHP/Blade/JS/Vue for translation keys, compares against lang files |
| `TranslationCoverageReport` | Report object with missing/unused keys, per-locale stats, text/JSON output |

### Console Commands

- `lang:coverage` -- Find missing and unused translation keys
- `lang:tm` -- Translation memory management (not fully read but registered)

## Architecture

- **Fallback chain**: `en_GB` -> `en` -> configured fallback. Built via `determineLocalesUsing()`.
- **Missing key validation**: Only in local/dev/testing. Logs at configurable level, triggers `trigger_deprecation` in local.
- **ICU formatter**: Caches compiled `MessageFormatter` instances (max 100, LRU eviction). Falls back to simple `{name}` placeholder replacement when intl is unavailable.
- **Fuzzy matching**: Combined algorithm weights: Levenshtein 0.25, token 0.50, n-gram 0.25. Confidence = similarity * 0.7 + quality * 0.3.
- **Translation Memory IDs**: Generated via `xxh128` hash of `sourceLocale:targetLocale:source`.

## Configuration

All under `config('core.lang.*')`:
- `fallback_chain`, `validate_keys`, `log_missing_keys`, `missing_key_log_level`
- `icu_enabled`
- `translation_memory.enabled`, `translation_memory.driver` (json/database), `translation_memory.fuzzy.*`

## Integration

- Translations loaded under the `core` namespace: `__('core::core.brand.name')`
- Override by publishing to `resources/lang/vendor/core/`
- Translation files live in `en_GB/core.php` within this directory
