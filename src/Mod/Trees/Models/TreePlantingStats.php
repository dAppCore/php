<?php

declare(strict_types=1);

namespace Core\Mod\Trees\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TreePlantingStats extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider',
        'model',
        'total_trees',
        'total_signups',
        'total_referrals',
        'date',
    ];

    protected $casts = [
        'total_trees' => 'integer',
        'total_signups' => 'integer',
        'total_referrals' => 'integer',
        'date' => 'date',
    ];

    /**
     * Increment stats for a provider/model on a given date, or create if not exists.
     *
     * Uses atomic upsert to handle concurrent access safely.
     */
    public static function incrementOrCreate(
        string $provider,
        ?string $model,
        int $trees = 1,
        int $signups = 0,
        int $referrals = 0,
        ?Carbon $date = null
    ): self {
        $date ??= today();
        $dateString = $date->toDateString();

        // Use upsert for atomic insert-or-update
        static::upsert(
            [
                [
                    'provider' => $provider,
                    'model' => $model,
                    'date' => $dateString,
                    'total_trees' => $trees,
                    'total_signups' => $signups,
                    'total_referrals' => $referrals,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ],
            ['provider', 'model', 'date'], // Unique key
            [] // Don't update anything on conflict - we'll increment below
        );

        // Fetch the record
        $stats = static::where([
            'provider' => $provider,
            'model' => $model,
            'date' => $dateString,
        ])->first();

        // If this was an existing record (not the one we just inserted), increment
        // Check if values need to be added (record already existed before our insert)
        if ($stats->total_trees !== $trees || $stats->total_signups !== $signups || $stats->total_referrals !== $referrals) {
            if ($trees > 0) {
                $stats->increment('total_trees', $trees);
            }
            if ($signups > 0) {
                $stats->increment('total_signups', $signups);
            }
            if ($referrals > 0) {
                $stats->increment('total_referrals', $referrals);
            }
            $stats = $stats->fresh();
        }

        return $stats;
    }

    /**
     * Increment referral count for a provider/model.
     */
    public static function incrementReferrals(
        string $provider,
        ?string $model,
        ?Carbon $date = null
    ): self {
        return static::incrementOrCreate($provider, $model, trees: 0, signups: 0, referrals: 1, date: $date);
    }

    /**
     * Scope to a specific provider.
     */
    public function scopeForProvider(Builder $query, string $provider): Builder
    {
        return $query->where('provider', $provider);
    }

    /**
     * Scope to a specific model.
     */
    public function scopeForModel(Builder $query, string $model): Builder
    {
        return $query->where('model', $model);
    }

    /**
     * Scope to this month's stats.
     */
    public function scopeThisMonth(Builder $query): Builder
    {
        return $query->whereMonth('date', now()->month)
            ->whereYear('date', now()->year);
    }

    /**
     * Scope to this year's stats.
     */
    public function scopeThisYear(Builder $query): Builder
    {
        return $query->whereYear('date', now()->year);
    }

    /**
     * Get total trees for a provider.
     */
    public static function getTotalTreesForProvider(string $provider): int
    {
        return static::forProvider($provider)->sum('total_trees');
    }

    /**
     * Get total trees for a specific model.
     */
    public static function getTotalTreesForModel(string $provider, string $model): int
    {
        return static::forProvider($provider)
            ->forModel($model)
            ->sum('total_trees');
    }

    /**
     * Get total signups for a provider.
     */
    public static function getTotalSignupsForProvider(string $provider): int
    {
        return static::forProvider($provider)->sum('total_signups');
    }

    /**
     * Get the leaderboard of providers by trees planted.
     */
    public static function getProviderLeaderboard(int $limit = 20): Collection
    {
        return static::query()
            ->selectRaw('provider, SUM(total_trees) as trees, SUM(total_signups) as signups')
            ->groupBy('provider')
            ->orderByDesc('trees')
            ->limit($limit)
            ->get();
    }

    /**
     * Get model breakdown for a provider.
     */
    public static function getModelBreakdown(string $provider): Collection
    {
        return static::forProvider($provider)
            ->whereNotNull('model')
            ->selectRaw('model, SUM(total_trees) as trees, SUM(total_signups) as signups')
            ->groupBy('model')
            ->orderByDesc('trees')
            ->get();
    }

    /**
     * Get global totals.
     */
    public static function getGlobalTotals(): array
    {
        $query = static::query();

        return [
            'total_trees' => (int) $query->sum('total_trees'),
            'trees_this_month' => (int) static::thisMonth()->sum('total_trees'),
            'trees_this_year' => (int) static::thisYear()->sum('total_trees'),
            'total_signups' => (int) $query->sum('total_signups'),
            'total_referrals' => (int) $query->sum('total_referrals'),
        ];
    }
}
