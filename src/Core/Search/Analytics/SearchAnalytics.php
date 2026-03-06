<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Search\Analytics;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Search Analytics Service.
 *
 * Tracks search queries, results, and user interactions for analysis.
 * Helps understand what users are searching for and how to improve results.
 *
 * Features:
 * - Query tracking with timestamps and result counts
 * - Click-through tracking for search results
 * - Zero-result query tracking for content gap analysis
 * - Popular search trending
 * - User session tracking (optional)
 *
 * Configuration in config/search.php:
 *   'analytics' => [
 *       'enabled' => true,
 *       'track_clicks' => true,
 *       'track_sessions' => false,
 *       'retention_days' => 90,
 *       'exclude_patterns' => ['password', 'secret'],
 *   ]
 */
class SearchAnalytics
{
    /**
     * Table name for search analytics.
     */
    protected const TABLE = 'search_analytics';

    /**
     * Whether analytics tracking is enabled.
     */
    protected bool $enabled;

    /**
     * Whether to track result clicks.
     */
    protected bool $trackClicks;

    /**
     * Whether to track user sessions.
     */
    protected bool $trackSessions;

    /**
     * Patterns to exclude from tracking (for privacy).
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
        $this->enabled = config('search.analytics.enabled', true);
        $this->trackClicks = config('search.analytics.track_clicks', true);
        $this->trackSessions = config('search.analytics.track_sessions', false);
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
     * Check if analytics is enabled and the table exists.
     */
    public function isEnabled(): bool
    {
        if (! $this->enabled) {
            return false;
        }

        return $this->tableExists();
    }

    /**
     * Check if the analytics table exists (cached).
     */
    protected function tableExists(): bool
    {
        if ($this->tableExists !== null) {
            return $this->tableExists;
        }

        $this->tableExists = Cache::remember(
            'search_analytics:table_exists',
            300,
            fn () => Schema::hasTable(self::TABLE)
        );

        return $this->tableExists;
    }

    /**
     * Track a search query.
     *
     * @param  string  $query  The search query
     * @param  int  $resultCount  Number of results returned
     * @param  array<string>  $types  Search types filtered
     * @param  float|null  $duration  Search duration in milliseconds
     * @param  string|null  $sessionId  Optional session identifier
     * @param  array<string, mixed>  $metadata  Additional metadata
     */
    public function trackQuery(
        string $query,
        int $resultCount,
        array $types = [],
        ?float $duration = null,
        ?string $sessionId = null,
        array $metadata = []
    ): void {
        if (! $this->isEnabled()) {
            return;
        }

        // Skip if query matches exclusion patterns
        if ($this->shouldExclude($query)) {
            return;
        }

        try {
            DB::table(self::TABLE)->insert([
                'query' => $this->sanitizeQuery($query),
                'query_hash' => $this->hashQuery($query),
                'result_count' => $resultCount,
                'types' => ! empty($types) ? json_encode($types) : null,
                'duration_ms' => $duration,
                'session_id' => $this->trackSessions ? $sessionId : null,
                'user_id' => auth()->id(),
                'ip_hash' => $this->hashIp(request()->ip()),
                'metadata' => ! empty($metadata) ? json_encode($metadata) : null,
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to track search query', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Track a click on a search result.
     *
     * @param  string  $query  The original search query
     * @param  string  $resultType  The type of result clicked
     * @param  string  $resultId  The ID of the result clicked
     * @param  int  $position  Position in search results (1-indexed)
     * @param  string|null  $sessionId  Optional session identifier
     */
    public function trackClick(
        string $query,
        string $resultType,
        string $resultId,
        int $position,
        ?string $sessionId = null
    ): void {
        if (! $this->isEnabled() || ! $this->trackClicks) {
            return;
        }

        if ($this->shouldExclude($query)) {
            return;
        }

        try {
            DB::table('search_analytics_clicks')->insert([
                'query_hash' => $this->hashQuery($query),
                'result_type' => $resultType,
                'result_id' => $resultId,
                'position' => $position,
                'session_id' => $this->trackSessions ? $sessionId : null,
                'user_id' => auth()->id(),
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to track search click', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get popular search queries.
     *
     * @param  int  $limit  Maximum number of queries to return
     * @param  int  $days  Number of days to look back
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function getPopularQueries(int $limit = 10, int $days = 7): \Illuminate\Support\Collection
    {
        if (! $this->isEnabled()) {
            return collect();
        }

        return DB::table(self::TABLE)
            ->select('query', DB::raw('COUNT(*) as search_count'), DB::raw('AVG(result_count) as avg_results'))
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('query')
            ->orderByDesc('search_count')
            ->limit($limit)
            ->get();
    }

    /**
     * Get queries with zero results (content gaps).
     *
     * @param  int  $limit  Maximum number of queries to return
     * @param  int  $days  Number of days to look back
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function getZeroResultQueries(int $limit = 20, int $days = 30): \Illuminate\Support\Collection
    {
        if (! $this->isEnabled()) {
            return collect();
        }

        return DB::table(self::TABLE)
            ->select('query', DB::raw('COUNT(*) as search_count'))
            ->where('result_count', 0)
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('query')
            ->orderByDesc('search_count')
            ->limit($limit)
            ->get();
    }

    /**
     * Get search trend over time.
     *
     * @param  int  $days  Number of days to look back
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function getTrend(int $days = 30): \Illuminate\Support\Collection
    {
        if (! $this->isEnabled()) {
            return collect();
        }

        return DB::table(self::TABLE)
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as total_searches'),
                DB::raw('COUNT(DISTINCT query_hash) as unique_queries'),
                DB::raw('AVG(result_count) as avg_results'),
                DB::raw('AVG(duration_ms) as avg_duration_ms')
            )
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();
    }

    /**
     * Get click-through rate for searches.
     *
     * @param  int  $days  Number of days to look back
     * @return array{total_searches: int, searches_with_clicks: int, ctr: float}
     */
    public function getClickThroughRate(int $days = 30): array
    {
        if (! $this->isEnabled() || ! $this->trackClicks) {
            return [
                'total_searches' => 0,
                'searches_with_clicks' => 0,
                'ctr' => 0.0,
            ];
        }

        $totalSearches = DB::table(self::TABLE)
            ->where('created_at', '>=', now()->subDays($days))
            ->count();

        $searchesWithClicks = DB::table(self::TABLE)
            ->where('created_at', '>=', now()->subDays($days))
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('search_analytics_clicks')
                    ->whereColumn('search_analytics_clicks.query_hash', 'search_analytics.query_hash');
            })
            ->count();

        return [
            'total_searches' => $totalSearches,
            'searches_with_clicks' => $searchesWithClicks,
            'ctr' => $totalSearches > 0 ? round(($searchesWithClicks / $totalSearches) * 100, 2) : 0.0,
        ];
    }

    /**
     * Get average result position for clicks.
     *
     * @param  int  $days  Number of days to look back
     */
    public function getAverageClickPosition(int $days = 30): float
    {
        if (! $this->isEnabled() || ! $this->trackClicks) {
            return 0.0;
        }

        try {
            $result = DB::table('search_analytics_clicks')
                ->where('created_at', '>=', now()->subDays($days))
                ->avg('position');

            return round((float) ($result ?? 0), 2);
        } catch (\Exception $e) {
            return 0.0;
        }
    }

    /**
     * Prune old analytics records.
     *
     * @param  int|null  $days  Number of days to retain (null uses config)
     * @return int Number of records deleted
     */
    public function prune(?int $days = null): int
    {
        if (! $this->tableExists()) {
            return 0;
        }

        $days = $days ?? config('search.analytics.retention_days', 90);

        if ($days <= 0) {
            return 0;
        }

        $cutoff = now()->subDays($days);

        $deleted = DB::table(self::TABLE)
            ->where('created_at', '<', $cutoff)
            ->delete();

        // Also prune clicks if tracking is enabled
        if ($this->trackClicks && Schema::hasTable('search_analytics_clicks')) {
            DB::table('search_analytics_clicks')
                ->where('created_at', '<', $cutoff)
                ->delete();
        }

        return $deleted;
    }

    /**
     * Check if a query should be excluded from tracking.
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
     * Sanitize a query for storage.
     */
    protected function sanitizeQuery(string $query): string
    {
        // Truncate very long queries
        $query = mb_substr($query, 0, 255);

        // Normalize whitespace
        $query = preg_replace('/\s+/', ' ', trim($query));

        return $query ?? '';
    }

    /**
     * Create a hash of the query for grouping.
     */
    protected function hashQuery(string $query): string
    {
        return hash('xxh3', strtolower(trim($query)));
    }

    /**
     * Hash an IP address for privacy.
     */
    protected function hashIp(?string $ip): ?string
    {
        if ($ip === null) {
            return null;
        }

        // Use a daily rotating salt for privacy
        $salt = config('app.key').date('Y-m-d');

        return hash('sha256', $ip.$salt);
    }
}
