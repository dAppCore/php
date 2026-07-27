# Core\Search

Unified search across system components with analytics tracking, autocomplete suggestions, and result highlighting.

## Key Classes

| Class | Purpose |
|-------|---------|
| `Unified` | Main search service: searches MCP tools/resources, API endpoints, patterns, assets, todos, agent plans |
| `Analytics\SearchAnalytics` | Tracks queries, clicks, zero-result queries, trends. Privacy-aware (daily-rotating IP hashes, sensitive pattern exclusion) |
| `Suggestions\SearchSuggestions` | Autocomplete from popular queries, recent searches, and content. Logarithmic popularity scoring |
| `Support\SearchHighlighter` | Highlights matched terms in results with configurable HTML wrapper, snippet extraction with context |
| `Boot` | Service provider: merges config, registers singletons, loads migrations |

## Search Sources (Unified)

| Type | Source | Model/Data |
|------|--------|------------|
| `mcp_tool` | YAML files | `resource_path('mcp/servers/*.yaml')` |
| `mcp_resource` | YAML files | Same |
| `api_endpoint` | Config | `config('core.search.api_endpoints')` |
| `pattern` | Database | `Core\Mod\Uptelligence\Models\Pattern` |
| `asset` | Database | `Core\Mod\Uptelligence\Models\Asset` |
| `todo` | Database | `Core\Mod\Uptelligence\Models\UpstreamTodo` |
| `plan` | Database | `Core\Mod\Agentic\Models\AgentPlan` |

## Scoring Algorithm

Configurable weights via `config/search.php`:
- Exact match: 20 (30 if field equals query exactly)
- Starts-with: 15
- Word match: 5 (7.5 for exact word)
- Position factor: earlier fields in the array score higher
- Fuzzy: Levenshtein distance, 0.5x score multiplier, min 4 char query

## Analytics

- Queries stored in `search_analytics` table with `query_hash` (xxh3) for grouping
- Click tracking in `search_analytics_clicks`
- Sensitive patterns excluded: password, secret, token, key, credit, ssn
- IP hashed with daily-rotating salt
- `prune()` respects `retention_days` config (default 90)

## Suggestions

Three sources (configurable priority): `popular`, `recent`, `content`
- Popular: From analytics, log-scale scoring, queries with results only
- Recent: Per-user cache (30 days), session fallback for guests
- Content: Prefix matching on model names
- Trending: Growth comparison between recent and earlier period

## Configuration

All in `config/search.php` (publishable):
- `scoring.*` -- match weights
- `fuzzy.*` -- Levenshtein settings
- `suggestions.*` -- autocomplete settings
- `analytics.*` -- tracking and retention

## LIKE injection protection

`escapeLikeQuery()` escapes `%` and `_` wildcards. If more than 3 wildcards, strips them entirely to prevent DoS.
