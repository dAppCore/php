<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Seo\Analytics;

use Core\Seo\Models\SeoScoreHistory;
use Core\Seo\SeoMetadata;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * SEO Score Trend Tracking Service.
 *
 * Tracks SEO scores over time to show improvement or regression trends.
 * Provides methods for recording scores, querying trends, and generating
 * analytics reports.
 *
 * Features:
 * - Automatic score recording from SeoMetadata models
 * - Daily and weekly aggregation for trend analysis
 * - Score improvement/regression detection
 * - Site-wide score statistics
 * - Configurable retention policy
 *
 * Configuration in config/seo.php:
 *   'trends' => [
 *       'enabled' => true,
 *       'retention_days' => 90,
 *       'record_on_save' => true,
 *       'min_interval_hours' => 24,
 *   ]
 *
 * Usage:
 *   $trend = new SeoScoreTrend();
 *   $trend->recordScore($seoMetadata);
 *   $history = $trend->getHistory($seoMetadata);
 *   $stats = $trend->getSiteStats();
 */
class SeoScoreTrend
{
    /**
     * Table name for score history.
     */
    protected const TABLE = 'seo_score_history';

    /**
     * Whether trend tracking is enabled.
     */
    protected bool $enabled;

    /**
     * Minimum interval between recordings (hours).
     */
    protected int $minIntervalHours;

    /**
     * Whether the history table exists (cached).
     */
    protected ?bool $tableExists = null;

    public function __construct()
    {
        $this->enabled = config('seo.trends.enabled', true);
        $this->minIntervalHours = config('seo.trends.min_interval_hours', 24);
    }

    /**
     * Check if trend tracking is enabled and table exists.
     */
    public function isEnabled(): bool
    {
        if (! $this->enabled) {
            return false;
        }

        return $this->tableExists();
    }

    /**
     * Record the current score for an SEO metadata record.
     *
     * @param  SeoMetadata  $metadata  The SEO metadata to record
     * @param  bool  $force  Force recording even if within min interval
     * @return SeoScoreHistory|null The created record or null if skipped
     */
    public function recordScore(SeoMetadata $metadata, bool $force = false): ?SeoScoreHistory
    {
        if (! $this->isEnabled()) {
            return null;
        }

        // Skip if score is null
        if ($metadata->seo_score === null) {
            return null;
        }

        // Check if we should skip due to minimum interval
        if (! $force && ! $this->shouldRecord($metadata)) {
            return null;
        }

        try {
            return SeoScoreHistory::create([
                'seoable_type' => $metadata->seoable_type,
                'seoable_id' => $metadata->seoable_id,
                'seo_metadata_id' => $metadata->id,
                'score' => $metadata->seo_score,
                'issues' => $metadata->seo_issues,
                'suggestions' => $metadata->seo_suggestions,
                'snapshot' => $this->createSnapshot($metadata),
                'recorded_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to record SEO score history', [
                'metadata_id' => $metadata->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Record scores for multiple metadata records.
     *
     * @param  Collection<int, SeoMetadata>  $metadataRecords
     * @return int Number of records created
     */
    public function recordScores(Collection $metadataRecords): int
    {
        $count = 0;

        foreach ($metadataRecords as $metadata) {
            if ($this->recordScore($metadata) !== null) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Record scores for all metadata with scores.
     *
     * Useful for periodic batch recording (e.g., daily cron job).
     *
     * @return int Number of records created
     */
    public function recordAllScores(): int
    {
        if (! $this->isEnabled()) {
            return 0;
        }

        $metadata = SeoMetadata::whereNotNull('seo_score')->get();

        return $this->recordScores($metadata);
    }

    /**
     * Check if we should record based on minimum interval.
     */
    protected function shouldRecord(SeoMetadata $metadata): bool
    {
        if ($this->minIntervalHours <= 0) {
            return true;
        }

        $latest = SeoScoreHistory::latestForModel(
            $metadata->seoable_type,
            $metadata->seoable_id
        );

        if ($latest === null) {
            return true;
        }

        return $latest->recorded_at->addHours($this->minIntervalHours)->isPast();
    }

    /**
     * Create a snapshot of the metadata for historical reference.
     *
     * @return array<string, mixed>
     */
    protected function createSnapshot(SeoMetadata $metadata): array
    {
        return [
            'title' => $metadata->title,
            'description' => $metadata->description,
            'canonical_url' => $metadata->canonical_url,
            'focus_keyword' => $metadata->focus_keyword,
            'robots' => $metadata->robots,
            'has_og_image' => ! empty($metadata->og_data['image'] ?? null),
            'has_schema' => $metadata->hasSchemaMarkup(),
        ];
    }

    /**
     * Get score history for a model.
     *
     * @param  SeoMetadata|object  $model  The model or metadata
     * @param  int  $limit  Maximum records to return
     * @return Collection<int, SeoScoreHistory>
     */
    public function getHistory(object $model, int $limit = 100): Collection
    {
        if (! $this->isEnabled()) {
            return collect();
        }

        if ($model instanceof SeoMetadata) {
            return SeoScoreHistory::forModel(
                $model->seoable_type,
                $model->seoable_id,
                $limit
            );
        }

        // Assume it's a seoable model
        return SeoScoreHistory::forModel(
            get_class($model),
            $model->getKey(),
            $limit
        );
    }

    /**
     * Get daily score trend for a model.
     *
     * @param  SeoMetadata|object  $model  The model or metadata
     * @param  int  $days  Days to look back
     * @return Collection<int, object>
     */
    public function getDailyTrend(object $model, int $days = 30): Collection
    {
        if (! $this->isEnabled()) {
            return collect();
        }

        if ($model instanceof SeoMetadata) {
            return SeoScoreHistory::dailyAggregateForModel(
                $model->seoable_type,
                $model->seoable_id,
                $days
            );
        }

        return SeoScoreHistory::dailyAggregateForModel(
            get_class($model),
            $model->getKey(),
            $days
        );
    }

    /**
     * Get weekly score trend for a model.
     *
     * @param  SeoMetadata|object  $model  The model or metadata
     * @param  int  $weeks  Weeks to look back
     * @return Collection<int, object>
     */
    public function getWeeklyTrend(object $model, int $weeks = 12): Collection
    {
        if (! $this->isEnabled()) {
            return collect();
        }

        if ($model instanceof SeoMetadata) {
            return SeoScoreHistory::weeklyAggregateForModel(
                $model->seoable_type,
                $model->seoable_id,
                $weeks
            );
        }

        return SeoScoreHistory::weeklyAggregateForModel(
            get_class($model),
            $model->getKey(),
            $weeks
        );
    }

    /**
     * Get site-wide SEO statistics.
     *
     * @param  int  $days  Days to look back
     * @return array{
     *     current_avg: float,
     *     previous_avg: float,
     *     change: float,
     *     change_percent: float,
     *     total_pages: int,
     *     improving: int,
     *     declining: int,
     *     stable: int
     * }
     */
    public function getSiteStats(int $days = 7): array
    {
        if (! $this->isEnabled()) {
            return $this->emptyStats();
        }

        $cacheKey = 'seo_trend:site_stats:'.$days;

        return Cache::remember($cacheKey, 300, function () use ($days) {
            $recentPeriod = now()->subDays($days);
            $previousPeriod = now()->subDays($days * 2);

            // Current average
            $currentAvg = (float) DB::table(self::TABLE)
                ->where('recorded_at', '>=', $recentPeriod)
                ->avg('score') ?? 0;

            // Previous average
            $previousAvg = (float) DB::table(self::TABLE)
                ->whereBetween('recorded_at', [$previousPeriod, $recentPeriod])
                ->avg('score') ?? 0;

            $change = $currentAvg - $previousAvg;
            $changePercent = $previousAvg > 0 ? ($change / $previousAvg) * 100 : 0;

            // Get improving/declining/stable counts
            $improving = SeoScoreHistory::getImprovingModels($days);
            $declining = SeoScoreHistory::getDecliningModels($days);

            // Total unique pages tracked
            $totalPages = DB::table(self::TABLE)
                ->where('recorded_at', '>=', $recentPeriod)
                ->distinct()
                ->count(DB::raw('CONCAT(seoable_type, seoable_id)'));

            $stableCount = max(0, $totalPages - $improving->count() - $declining->count());

            return [
                'current_avg' => round($currentAvg, 1),
                'previous_avg' => round($previousAvg, 1),
                'change' => round($change, 1),
                'change_percent' => round($changePercent, 1),
                'total_pages' => $totalPages,
                'improving' => $improving->count(),
                'declining' => $declining->count(),
                'stable' => $stableCount,
            ];
        });
    }

    /**
     * Get score distribution statistics.
     *
     * @param  int  $days  Days to look back
     * @return array{
     *     excellent: int,
     *     good: int,
     *     fair: int,
     *     poor: int,
     *     distribution: array<string, int>
     * }
     */
    public function getScoreDistribution(int $days = 7): array
    {
        if (! $this->isEnabled()) {
            return [
                'excellent' => 0,
                'good' => 0,
                'fair' => 0,
                'poor' => 0,
                'distribution' => [],
            ];
        }

        $cacheKey = 'seo_trend:distribution:'.$days;

        return Cache::remember($cacheKey, 300, function () use ($days) {
            // Get the latest score for each unique model
            $latestScores = DB::table(self::TABLE.' as h1')
                ->select('h1.seoable_type', 'h1.seoable_id', 'h1.score')
                ->where('h1.recorded_at', '>=', now()->subDays($days))
                ->whereRaw('h1.recorded_at = (SELECT MAX(h2.recorded_at) FROM '.self::TABLE.' as h2 WHERE h2.seoable_type = h1.seoable_type AND h2.seoable_id = h1.seoable_id)')
                ->get();

            $excellent = $latestScores->filter(fn ($r) => $r->score >= 80)->count();
            $good = $latestScores->filter(fn ($r) => $r->score >= 60 && $r->score < 80)->count();
            $fair = $latestScores->filter(fn ($r) => $r->score >= 40 && $r->score < 60)->count();
            $poor = $latestScores->filter(fn ($r) => $r->score < 40)->count();

            // 10-point bucket distribution
            $distribution = [];
            for ($i = 0; $i <= 90; $i += 10) {
                $label = $i.'-'.($i + 9);
                $distribution[$label] = $latestScores->filter(
                    fn ($r) => $r->score >= $i && $r->score < $i + 10
                )->count();
            }
            $distribution['100'] = $latestScores->filter(fn ($r) => $r->score === 100)->count();

            return [
                'excellent' => $excellent,
                'good' => $good,
                'fair' => $fair,
                'poor' => $poor,
                'distribution' => $distribution,
            ];
        });
    }

    /**
     * Get top improving pages.
     *
     * @param  int  $limit  Maximum pages to return
     * @param  int  $days  Days in comparison period
     * @return Collection<int, object>
     */
    public function getTopImproving(int $limit = 10, int $days = 7): Collection
    {
        if (! $this->isEnabled()) {
            return collect();
        }

        return SeoScoreHistory::getImprovingModels($days)->take($limit);
    }

    /**
     * Get top declining pages.
     *
     * @param  int  $limit  Maximum pages to return
     * @param  int  $days  Days in comparison period
     * @return Collection<int, object>
     */
    public function getTopDeclining(int $limit = 10, int $days = 7): Collection
    {
        if (! $this->isEnabled()) {
            return collect();
        }

        return SeoScoreHistory::getDecliningModels($days)->take($limit);
    }

    /**
     * Get pages that need attention (low or declining scores).
     *
     * @param  int  $limit  Maximum pages to return
     * @param  int  $scoreThreshold  Score threshold (below = needs attention)
     * @return Collection<int, SeoScoreHistory>
     */
    public function getNeedsAttention(int $limit = 20, int $scoreThreshold = 50): Collection
    {
        if (! $this->isEnabled()) {
            return collect();
        }

        return SeoScoreHistory::belowThreshold($scoreThreshold)->take($limit);
    }

    /**
     * Get daily site-wide trend.
     *
     * @param  int  $days  Days to look back
     * @return Collection<int, object>
     */
    public function getSiteDailyTrend(int $days = 30): Collection
    {
        if (! $this->isEnabled()) {
            return collect();
        }

        return DB::table(self::TABLE)
            ->selectRaw('DATE(recorded_at) as date, AVG(score) as avg_score, MIN(score) as min_score, MAX(score) as max_score, COUNT(DISTINCT CONCAT(seoable_type, seoable_id)) as pages_tracked')
            ->where('recorded_at', '>=', now()->subDays($days))
            ->groupByRaw('DATE(recorded_at)')
            ->orderBy('date')
            ->get();
    }

    /**
     * Get common issues across the site.
     *
     * @param  int  $days  Days to look back
     * @param  int  $limit  Maximum issues to return
     * @return Collection<int, object>
     */
    public function getCommonIssues(int $days = 7, int $limit = 10): Collection
    {
        if (! $this->isEnabled()) {
            return collect();
        }

        // Get all issues from recent records
        $records = SeoScoreHistory::where('recorded_at', '>=', now()->subDays($days))
            ->whereNotNull('issues')
            ->get();

        // Count issue occurrences
        $issueCounts = collect();

        foreach ($records as $record) {
            foreach ($record->issues ?? [] as $issue) {
                $key = is_string($issue) ? $issue : ($issue['message'] ?? json_encode($issue));
                $issueCounts[$key] = ($issueCounts[$key] ?? 0) + 1;
            }
        }

        return $issueCounts
            ->map(fn ($count, $issue) => (object) ['issue' => $issue, 'count' => $count])
            ->sortByDesc('count')
            ->take($limit)
            ->values();
    }

    /**
     * Prune old history records.
     *
     * @param  int|null  $days  Days to retain (null uses config)
     * @return int Number of records deleted
     */
    public function prune(?int $days = null): int
    {
        if (! $this->tableExists()) {
            return 0;
        }

        $days = $days ?? config('seo.trends.retention_days', 90);

        return SeoScoreHistory::prune($days);
    }

    /**
     * Clear cached statistics.
     */
    public function clearCache(): void
    {
        Cache::forget('seo_trend:site_stats:7');
        Cache::forget('seo_trend:site_stats:30');
        Cache::forget('seo_trend:distribution:7');
        Cache::forget('seo_trend:distribution:30');
    }

    /**
     * Check if the history table exists (cached).
     */
    protected function tableExists(): bool
    {
        if ($this->tableExists !== null) {
            return $this->tableExists;
        }

        $this->tableExists = Cache::remember(
            'seo_score_history:table_exists',
            300,
            fn () => Schema::hasTable(self::TABLE)
        );

        return $this->tableExists;
    }

    /**
     * Return empty statistics array.
     *
     * @return array{
     *     current_avg: float,
     *     previous_avg: float,
     *     change: float,
     *     change_percent: float,
     *     total_pages: int,
     *     improving: int,
     *     declining: int,
     *     stable: int
     * }
     */
    protected function emptyStats(): array
    {
        return [
            'current_avg' => 0.0,
            'previous_avg' => 0.0,
            'change' => 0.0,
            'change_percent' => 0.0,
            'total_pages' => 0,
            'improving' => 0,
            'declining' => 0,
            'stable' => 0,
        ];
    }
}
