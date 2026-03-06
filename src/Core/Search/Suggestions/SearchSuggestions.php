<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Search\Suggestions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Search Suggestions and Autocomplete Service.
 *
 * Provides type-ahead suggestions based on:
 * - Popular search queries from search analytics
 * - Recent searches (per user or session)
 * - Prefix matching for instant suggestions
 * - Content-based suggestions from searchable items
 *
 * Features:
 * - Real-time prefix matching as user types
 * - Popularity-weighted suggestions
 * - User-specific recent searches
 * - Configurable suggestion sources
 * - Privacy-aware (respects exclude patterns)
 *
 * Configuration in config/search.php:
 *   'suggestions' => [
 *       'enabled' => true,
 *       'max_suggestions' => 10,
 *       'min_query_length' => 2,
 *       'cache_ttl' => 300,
 *       'track_recent' => true,
 *       'max_recent' => 20,
 *       'sources' => ['popular', 'recent', 'content'],
 *   ]
 *
 * @see \Core\Search\Analytics\SearchAnalytics For search tracking integration
 */
class SearchSuggestions
{
    /**
     * Table name for search analytics.
     */
    protected const ANALYTICS_TABLE = 'search_analytics';

    /**
     * Cache prefix for suggestions.
     */
    protected const CACHE_PREFIX = 'search_suggestions:';

    /**
     * Maximum suggestions to return.
     */
    protected int $maxSuggestions;

    /**
     * Minimum query length for suggestions.
     */
    protected int $minQueryLength;

    /**
     * Cache TTL in seconds.
     */
    protected int $cacheTtl;

    /**
     * Whether to track recent searches.
     */
    protected bool $trackRecent;

    /**
     * Maximum recent searches to store.
     */
    protected int $maxRecent;

    /**
     * Enabled suggestion sources.
     *
     * @var array<string>
     */
    protected array $sources;

    /**
     * Patterns to exclude from suggestions (for privacy).
     *
     * @var array<string>
     */
    protected array $excludePatterns;

    /**
     * Whether the analytics table exists.
     */
    protected ?bool $tableExists = null;

    public function __construct()
    {
        $this->maxSuggestions = config('search.suggestions.max_suggestions', 10);
        $this->minQueryLength = config('search.suggestions.min_query_length', 2);
        $this->cacheTtl = config('search.suggestions.cache_ttl', 300);
        $this->trackRecent = config('search.suggestions.track_recent', true);
        $this->maxRecent = config('search.suggestions.max_recent', 20);
        $this->sources = config('search.suggestions.sources', ['popular', 'recent', 'content']);
        $this->excludePatterns = config('search.analytics.exclude_patterns', [
            'password',
            'secret',
            'token',
            'key',
            'credit',
            'ssn',
        ]);
    }

    /**
     * Check if suggestions are enabled.
     */
    public function isEnabled(): bool
    {
        return config('search.suggestions.enabled', true);
    }

    /**
     * Get suggestions for a partial query.
     *
     * @param  string  $query  The partial search query
     * @param  int|null  $limit  Maximum suggestions (null uses config default)
     * @param  array<string>|null  $sources  Sources to use (null uses config default)
     * @return Collection<int, array{text: string, type: string, score: float, metadata: array}>
     */
    public function suggest(string $query, ?int $limit = null, ?array $sources = null): Collection
    {
        $query = strtolower(trim($query));
        $limit = $limit ?? $this->maxSuggestions;
        $sources = $sources ?? $this->sources;

        if (! $this->isEnabled() || strlen($query) < $this->minQueryLength) {
            return collect();
        }

        // Check for excluded patterns
        if ($this->shouldExclude($query)) {
            return collect();
        }

        $suggestions = collect();

        // Gather suggestions from each enabled source
        if (in_array('popular', $sources)) {
            $suggestions = $suggestions->merge($this->getPopularSuggestions($query, $limit));
        }

        if (in_array('recent', $sources)) {
            $suggestions = $suggestions->merge($this->getRecentSuggestions($query, $limit));
        }

        if (in_array('content', $sources)) {
            $suggestions = $suggestions->merge($this->getContentSuggestions($query, $limit));
        }

        // Deduplicate, sort by score, and limit
        return $suggestions
            ->unique('text')
            ->sortByDesc('score')
            ->take($limit)
            ->values();
    }

    /**
     * Get popular query suggestions based on search analytics.
     *
     * @param  string  $prefix  The query prefix to match
     * @param  int  $limit  Maximum suggestions
     * @return Collection<int, array{text: string, type: string, score: float, metadata: array}>
     */
    public function getPopularSuggestions(string $prefix, int $limit = 10): Collection
    {
        if (! $this->analyticsTableExists()) {
            return collect();
        }

        $cacheKey = self::CACHE_PREFIX."popular:{$prefix}:{$limit}";

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($prefix, $limit) {
            $escaped = $this->escapeLikeQuery($prefix);

            return DB::table(self::ANALYTICS_TABLE)
                ->select('query', DB::raw('COUNT(*) as search_count'))
                ->where('query', 'like', "{$escaped}%")
                ->where('result_count', '>', 0) // Only suggest queries that had results
                ->where('created_at', '>=', now()->subDays(30))
                ->groupBy('query')
                ->orderByDesc('search_count')
                ->limit($limit * 2) // Get more to account for filtering
                ->get()
                ->filter(fn ($row) => ! $this->shouldExclude($row->query))
                ->take($limit)
                ->map(fn ($row) => [
                    'text' => $row->query,
                    'type' => 'popular',
                    'score' => $this->calculatePopularityScore($row->search_count),
                    'metadata' => [
                        'search_count' => (int) $row->search_count,
                    ],
                ])
                ->values();
        });
    }

    /**
     * Get recent search suggestions for the current user/session.
     *
     * @param  string  $prefix  The query prefix to match
     * @param  int  $limit  Maximum suggestions
     * @return Collection<int, array{text: string, type: string, score: float, metadata: array}>
     */
    public function getRecentSuggestions(string $prefix, int $limit = 10): Collection
    {
        if (! $this->trackRecent) {
            return collect();
        }

        $recentSearches = $this->getRecentSearches();

        return $recentSearches
            ->filter(fn ($search) => str_starts_with(strtolower($search['query']), $prefix))
            ->filter(fn ($search) => ! $this->shouldExclude($search['query']))
            ->take($limit)
            ->map(fn ($search, $index) => [
                'text' => $search['query'],
                'type' => 'recent',
                'score' => 100 - $index, // More recent = higher score
                'metadata' => [
                    'searched_at' => $search['searched_at'],
                ],
            ])
            ->values();
    }

    /**
     * Get content-based suggestions from searchable items.
     *
     * This searches titles/names of searchable content for prefix matches.
     *
     * @param  string  $prefix  The query prefix to match
     * @param  int  $limit  Maximum suggestions
     * @return Collection<int, array{text: string, type: string, score: float, metadata: array}>
     */
    public function getContentSuggestions(string $prefix, int $limit = 10): Collection
    {
        $suggestions = collect();
        $escaped = $this->escapeLikeQuery($prefix);

        // Search patterns if available
        if (class_exists(\Core\Mod\Uptelligence\Models\Pattern::class)) {
            try {
                $patterns = \Core\Mod\Uptelligence\Models\Pattern::where('name', 'like', "{$escaped}%")
                    ->limit($limit)
                    ->pluck('name')
                    ->map(fn ($name) => [
                        'text' => strtolower($name),
                        'type' => 'content',
                        'score' => 50,
                        'metadata' => ['source' => 'pattern'],
                    ]);

                $suggestions = $suggestions->merge($patterns);
            } catch (\Exception $e) {
                // Ignore errors
            }
        }

        // Search assets if available
        if (class_exists(\Core\Mod\Uptelligence\Models\Asset::class)) {
            try {
                $assets = \Core\Mod\Uptelligence\Models\Asset::where('name', 'like', "{$escaped}%")
                    ->limit($limit)
                    ->pluck('name')
                    ->map(fn ($name) => [
                        'text' => strtolower($name),
                        'type' => 'content',
                        'score' => 50,
                        'metadata' => ['source' => 'asset'],
                    ]);

                $suggestions = $suggestions->merge($assets);
            } catch (\Exception $e) {
                // Ignore errors
            }
        }

        return $suggestions->take($limit)->values();
    }

    /**
     * Record a search query in the user's recent searches.
     *
     * @param  string  $query  The search query to record
     */
    public function recordRecentSearch(string $query): void
    {
        if (! $this->trackRecent) {
            return;
        }

        $query = trim($query);
        if (empty($query) || $this->shouldExclude($query)) {
            return;
        }

        $key = $this->getRecentSearchesKey();
        $recent = $this->getRecentSearches();

        // Remove if already exists (will be re-added at top)
        $recent = $recent->filter(fn ($s) => strtolower($s['query']) !== strtolower($query));

        // Add to beginning
        $recent = $recent->prepend([
            'query' => $query,
            'searched_at' => now()->toIso8601String(),
        ]);

        // Trim to max size
        $recent = $recent->take($this->maxRecent);

        Cache::put($key, $recent->toArray(), now()->addDays(30));
    }

    /**
     * Get the user's recent searches.
     *
     * @return Collection<int, array{query: string, searched_at: string}>
     */
    public function getRecentSearches(): Collection
    {
        $key = $this->getRecentSearchesKey();

        return collect(Cache::get($key, []));
    }

    /**
     * Clear the user's recent searches.
     */
    public function clearRecentSearches(): void
    {
        Cache::forget($this->getRecentSearchesKey());
    }

    /**
     * Get trending searches (queries with increasing popularity).
     *
     * @param  int  $limit  Maximum trending queries
     * @param  int  $days  Days to analyze
     * @return Collection<int, array{query: string, current_count: int, previous_count: int, growth: float}>
     */
    public function getTrendingSuggestions(int $limit = 10, int $days = 7): Collection
    {
        if (! $this->analyticsTableExists()) {
            return collect();
        }

        $cacheKey = self::CACHE_PREFIX."trending:{$limit}:{$days}";

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($limit, $days) {
            $midpoint = now()->subDays($days / 2);

            // Get counts for recent period
            $recent = DB::table(self::ANALYTICS_TABLE)
                ->select('query', DB::raw('COUNT(*) as count'))
                ->where('created_at', '>=', $midpoint)
                ->where('result_count', '>', 0)
                ->groupBy('query')
                ->pluck('count', 'query');

            // Get counts for earlier period
            $earlier = DB::table(self::ANALYTICS_TABLE)
                ->select('query', DB::raw('COUNT(*) as count'))
                ->where('created_at', '>=', now()->subDays($days))
                ->where('created_at', '<', $midpoint)
                ->where('result_count', '>', 0)
                ->groupBy('query')
                ->pluck('count', 'query');

            // Calculate growth
            return $recent->map(function ($count, $query) use ($earlier) {
                $previousCount = $earlier->get($query, 0);
                $growth = $previousCount > 0
                    ? (($count - $previousCount) / $previousCount) * 100
                    : ($count > 1 ? 100 : 0);

                return [
                    'query' => $query,
                    'current_count' => (int) $count,
                    'previous_count' => (int) $previousCount,
                    'growth' => round($growth, 2),
                ];
            })
                ->filter(fn ($item) => $item['growth'] > 0 && ! $this->shouldExclude($item['query']))
                ->sortByDesc('growth')
                ->take($limit)
                ->values();
        });
    }

    /**
     * Get the cache key for the current user's recent searches.
     */
    protected function getRecentSearchesKey(): string
    {
        $userId = auth()->id();

        if ($userId) {
            return self::CACHE_PREFIX."recent:user:{$userId}";
        }

        // Fall back to session ID for guests
        return self::CACHE_PREFIX.'recent:session:'.session()->getId();
    }

    /**
     * Calculate popularity score based on search count.
     *
     * Uses logarithmic scaling to prevent extremely popular queries
     * from dominating all suggestions.
     */
    protected function calculatePopularityScore(int $searchCount): float
    {
        // Log scale: 1 search = 10, 10 searches = 20, 100 searches = 30, etc.
        return 10 * (1 + log10(max(1, $searchCount)));
    }

    /**
     * Check if a query should be excluded from suggestions.
     */
    protected function shouldExclude(string $query): bool
    {
        $lowerQuery = strtolower($query);

        foreach ($this->excludePatterns as $pattern) {
            if (str_contains($lowerQuery, strtolower($pattern))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Escape special LIKE wildcards in query.
     */
    protected function escapeLikeQuery(string $query): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query);
    }

    /**
     * Check if the analytics table exists (cached).
     */
    protected function analyticsTableExists(): bool
    {
        if ($this->tableExists !== null) {
            return $this->tableExists;
        }

        $this->tableExists = Cache::remember(
            self::CACHE_PREFIX.'table_exists',
            300,
            fn () => Schema::hasTable(self::ANALYTICS_TABLE)
        );

        return $this->tableExists;
    }

    /**
     * Invalidate all suggestion caches.
     */
    public function clearCache(): void
    {
        // Clear popular suggestions cache
        Cache::forget(self::CACHE_PREFIX.'table_exists');

        // Note: Individual prefix caches will expire naturally
        // For full cache clear, use cache:clear artisan command
    }

    /**
     * Get configuration for the suggestion system.
     *
     * @return array{enabled: bool, max_suggestions: int, min_query_length: int, sources: array}
     */
    public function getConfig(): array
    {
        return [
            'enabled' => $this->isEnabled(),
            'max_suggestions' => $this->maxSuggestions,
            'min_query_length' => $this->minQueryLength,
            'sources' => $this->sources,
            'track_recent' => $this->trackRecent,
            'cache_ttl' => $this->cacheTtl,
        ];
    }
}
