# Lang/TranslationMemory/Contracts/ — TM Repository Interface

## Interfaces

| Interface | Purpose |
|-----------|---------|
| `TranslationMemoryRepository` | Storage backend contract. Methods: `store(entry)`, `find(source, locale)`, `search(query, locale)`, `all(locale)`, `delete(id)`. Implementations may use JSON files, databases, or external services. |

The default implementation is `JsonTranslationMemoryRepository`.
