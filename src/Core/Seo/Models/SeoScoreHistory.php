<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;

/**
 * Historical SEO score record.
 *
 * Stores point-in-time snapshots of SEO scores and issues for tracking
 * improvement or regression over time. Supports daily and weekly aggregation
 * for trend analysis.
 *
 * Database schema:
 * - id: Primary key
 * - seoable_type: Polymorphic model type
 * - seoable_id: Polymorphic model ID
 * - seo_metadata_id: Optional link to seo_metadata record
 * - score: SEO score (0-100)
 * - issues: JSON array of issues at this point in time
 * - suggestions: JSON array of suggestions
 * - snapshot: Full metadata snapshot for detailed comparison
 * - recorded_at: When this score was recorded
 *
 * @property int $id
 * @property string $seoable_type
 * @property int $seoable_id
 * @property int|null $seo_metadata_id
 * @property int $score
 * @property array<string>|null $issues
 * @property array<string>|null $suggestions
 * @property array<string, mixed>|null $snapshot
 * @property \Carbon\Carbon $recorded_at
 * @property \Carbon\Carbon $created_at
 */
class SeoScoreHistory extends Model
{
    /**
     * Table name.
     */
    protected $table = 'seo_score_history';

    /**
     * Disable updated_at since records are immutable.
     */
    public const UPDATED_AT = null;

    /**
     * Fillable attributes.
     *
     * @var array<string>
     */
    protected $fillable = [
        'seoable_type',
        'seoable_id',
        'seo_metadata_id',
        'score',
        'issues',
        'suggestions',
        'snapshot',
        'recorded_at',
    ];

    /**
     * Attribute casting.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'score' => 'integer',
        'issues' => 'array',
        'suggestions' => 'array',
        'snapshot' => 'array',
        'recorded_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    /**
     * Get the seoable model.
     */
    public function seoable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the associated SEO metadata record.
     */
    public function seoMetadata(): BelongsTo
    {
        return $this->belongsTo(\Core\Seo\SeoMetadata::class, 'seo_metadata_id');
    }

    /**
     * Get the score color for UI display.
     */
    public function getScoreColorAttribute(): string
    {
        return match (true) {
            $this->score >= 80 => 'green',
            $this->score >= 50 => 'amber',
            default => 'red',
        };
    }

    /**
     * Get the issue count.
     */
    public function getIssueCountAttribute(): int
    {
        return count($this->issues ?? []);
    }

    /**
     * Check if score improved from previous record.
     *
     * @return bool|null True if improved, false if regressed, null if no previous
     */
    public function hasImproved(): ?bool
    {
        $previous = static::where('seoable_type', $this->seoable_type)
            ->where('seoable_id', $this->seoable_id)
            ->where('recorded_at', '<', $this->recorded_at)
            ->orderByDesc('recorded_at')
            ->first();

        if ($previous === null) {
            return null;
        }

        return $this->score > $previous->score;
    }

    /**
     * Get the score change from previous record.
     */
    public function getScoreChange(): ?int
    {
        $previous = static::where('seoable_type', $this->seoable_type)
            ->where('seoable_id', $this->seoable_id)
            ->where('recorded_at', '<', $this->recorded_at)
            ->orderByDesc('recorded_at')
            ->first();

        if ($previous === null) {
            return null;
        }

        return $this->score - $previous->score;
    }

    /**
     * Get history for a specific model.
     *
     * @param  int  $limit  Maximum records to return
     * @return Collection<int, static>
     */
    public static function forModel(string $type, int $id, int $limit = 100): Collection
    {
        return static::where('seoable_type', $type)
            ->where('seoable_id', $id)
            ->orderByDesc('recorded_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Get daily aggregated scores for a model.
     *
     * @param  int  $days  Number of days to look back
     * @return Collection<int, object>
     */
    public static function dailyAggregateForModel(string $type, int $id, int $days = 30): Collection
    {
        return static::selectRaw('DATE(recorded_at) as date, AVG(score) as avg_score, MIN(score) as min_score, MAX(score) as max_score, COUNT(*) as count')
            ->where('seoable_type', $type)
            ->where('seoable_id', $id)
            ->where('recorded_at', '>=', now()->subDays($days))
            ->groupByRaw('DATE(recorded_at)')
            ->orderBy('date')
            ->get();
    }

    /**
     * Get weekly aggregated scores for a model.
     *
     * @param  int  $weeks  Number of weeks to look back
     * @return Collection<int, object>
     */
    public static function weeklyAggregateForModel(string $type, int $id, int $weeks = 12): Collection
    {
        return static::selectRaw('YEARWEEK(recorded_at, 1) as yearweek, AVG(score) as avg_score, MIN(score) as min_score, MAX(score) as max_score, COUNT(*) as count')
            ->where('seoable_type', $type)
            ->where('seoable_id', $id)
            ->where('recorded_at', '>=', now()->subWeeks($weeks))
            ->groupByRaw('YEARWEEK(recorded_at, 1)')
            ->orderBy('yearweek')
            ->get();
    }

    /**
     * Get the latest score for a model.
     */
    public static function latestForModel(string $type, int $id): ?static
    {
        return static::where('seoable_type', $type)
            ->where('seoable_id', $id)
            ->orderByDesc('recorded_at')
            ->first();
    }

    /**
     * Get scores recorded within a date range.
     *
     * @param  \Carbon\Carbon  $from  Start date
     * @param  \Carbon\Carbon  $to  End date
     * @return Collection<int, static>
     */
    public static function inDateRange(\Carbon\Carbon $from, \Carbon\Carbon $to): Collection
    {
        return static::whereBetween('recorded_at', [$from, $to])
            ->orderByDesc('recorded_at')
            ->get();
    }

    /**
     * Get scores below a threshold.
     *
     * @param  int  $threshold  Score threshold (default 50)
     * @param  int  $days  Days to look back
     * @return Collection<int, static>
     */
    public static function belowThreshold(int $threshold = 50, int $days = 7): Collection
    {
        return static::where('score', '<', $threshold)
            ->where('recorded_at', '>=', now()->subDays($days))
            ->orderBy('score')
            ->get();
    }

    /**
     * Get models with improving scores.
     *
     * Compares average score in recent period vs previous period.
     *
     * @param  int  $days  Days in each comparison period
     * @return Collection<int, object>
     */
    public static function getImprovingModels(int $days = 7): Collection
    {
        $recentPeriod = now()->subDays($days);
        $previousPeriod = now()->subDays($days * 2);

        // Get average scores for both periods
        $recent = static::selectRaw('seoable_type, seoable_id, AVG(score) as avg_score')
            ->where('recorded_at', '>=', $recentPeriod)
            ->groupBy('seoable_type', 'seoable_id')
            ->get()
            ->keyBy(fn ($r) => $r->seoable_type.'|'.$r->seoable_id);

        $previous = static::selectRaw('seoable_type, seoable_id, AVG(score) as avg_score')
            ->whereBetween('recorded_at', [$previousPeriod, $recentPeriod])
            ->groupBy('seoable_type', 'seoable_id')
            ->get()
            ->keyBy(fn ($r) => $r->seoable_type.'|'.$r->seoable_id);

        // Find improving models
        $improving = collect();

        foreach ($recent as $key => $recentData) {
            if (isset($previous[$key])) {
                $change = $recentData->avg_score - $previous[$key]->avg_score;
                if ($change > 0) {
                    $improving->push((object) [
                        'seoable_type' => $recentData->seoable_type,
                        'seoable_id' => $recentData->seoable_id,
                        'recent_avg' => round($recentData->avg_score, 1),
                        'previous_avg' => round($previous[$key]->avg_score, 1),
                        'change' => round($change, 1),
                    ]);
                }
            }
        }

        return $improving->sortByDesc('change')->values();
    }

    /**
     * Get models with declining scores.
     *
     * @param  int  $days  Days in each comparison period
     * @return Collection<int, object>
     */
    public static function getDecliningModels(int $days = 7): Collection
    {
        $recentPeriod = now()->subDays($days);
        $previousPeriod = now()->subDays($days * 2);

        $recent = static::selectRaw('seoable_type, seoable_id, AVG(score) as avg_score')
            ->where('recorded_at', '>=', $recentPeriod)
            ->groupBy('seoable_type', 'seoable_id')
            ->get()
            ->keyBy(fn ($r) => $r->seoable_type.'|'.$r->seoable_id);

        $previous = static::selectRaw('seoable_type, seoable_id, AVG(score) as avg_score')
            ->whereBetween('recorded_at', [$previousPeriod, $recentPeriod])
            ->groupBy('seoable_type', 'seoable_id')
            ->get()
            ->keyBy(fn ($r) => $r->seoable_type.'|'.$r->seoable_id);

        $declining = collect();

        foreach ($recent as $key => $recentData) {
            if (isset($previous[$key])) {
                $change = $recentData->avg_score - $previous[$key]->avg_score;
                if ($change < 0) {
                    $declining->push((object) [
                        'seoable_type' => $recentData->seoable_type,
                        'seoable_id' => $recentData->seoable_id,
                        'recent_avg' => round($recentData->avg_score, 1),
                        'previous_avg' => round($previous[$key]->avg_score, 1),
                        'change' => round($change, 1),
                    ]);
                }
            }
        }

        return $declining->sortBy('change')->values();
    }

    /**
     * Prune old history records.
     *
     * @param  int  $days  Days to retain
     * @return int Number of records deleted
     */
    public static function prune(int $days = 90): int
    {
        return static::where('recorded_at', '<', now()->subDays($days))->delete();
    }
}
