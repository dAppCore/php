# Search/Analytics/ — Search Analytics

## Files

| File | Purpose |
|------|---------|
| `SearchAnalytics` | Tracks search queries, results, and user interactions. Features: query tracking with timestamps and result counts, click-through tracking, zero-result query tracking for content gap analysis, popular search trending. |

Part of the Search subsystem. Data feeds into `SearchSuggestions` for autocomplete.

## Migrations

| File | Purpose |
|------|---------|
| `migrations/2024_01_01_000001_create_search_analytics_tables.php` | Creates tables for search query tracking, result counts, and click-through data. |
