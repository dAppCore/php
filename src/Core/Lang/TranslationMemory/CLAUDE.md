# Lang/TranslationMemory/ — Translation Memory System

Stores and retrieves previous translations for reuse and consistency.

## Files

| File | Purpose |
|------|---------|
| `TranslationMemory` | Unified service — store, retrieve, suggest translations. Supports exact and fuzzy matching with confidence scoring. |
| `TranslationMemoryEntry` | DTO for a single translation unit — source text, target text, metadata, quality score (0.0-1.0). |
| `FuzzyMatcher` | Finds similar translations via Levenshtein distance, token/word matching, and n-gram matching. Combines with quality score for confidence rating. |
| `JsonTranslationMemoryRepository` | File-based storage backend — JSON files organised by locale pairs. |
| `TmxExporter` | Exports to TMX (Translation Memory eXchange) standard XML format. |
| `TmxImporter` | Imports from TMX files for interoperability with other translation tools. |
