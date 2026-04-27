# Search/Suggestions/ — Search Autocomplete

## Files

| File | Purpose |
|------|---------|
| `SearchSuggestions` | Type-ahead suggestion service. Sources: popular queries from SearchAnalytics, recent searches (per user/session), prefix matching, content-based suggestions from searchable items. Cached for performance. |

Part of the Search subsystem. Provides autocomplete data for search UIs.
