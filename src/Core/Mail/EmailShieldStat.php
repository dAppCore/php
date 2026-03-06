<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Mail;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Email Shield Statistics Model
 *
 * Tracks daily email validation statistics including valid, invalid, and disposable email counts.
 *
 * @property int $id
 * @property Carbon $date
 * @property int $valid_count
 * @property int $invalid_count
 * @property int $disposable_count
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class EmailShieldStat extends Model
{
    use HasFactory;

    protected $table = 'email_shield_stats';

    protected $fillable = [
        'date',
        'valid_count',
        'invalid_count',
        'disposable_count',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'valid_count' => 'integer',
        'invalid_count' => 'integer',
        'disposable_count' => 'integer',
    ];

    /**
     * Scope query to a specific date range.
     */
    public function scopeForDateRange(Builder $query, Carbon $from, Carbon $to): Builder
    {
        return $query->whereBetween('date', [
            $from->format('Y-m-d'),
            $to->format('Y-m-d'),
        ]);
    }

    /**
     * Increment the valid email count for today.
     */
    public static function incrementValid(): void
    {
        static::incrementCounter('valid_count');
    }

    /**
     * Increment the invalid email count for today.
     */
    public static function incrementInvalid(): void
    {
        static::incrementCounter('invalid_count');
    }

    /**
     * Increment the disposable email count for today.
     */
    public static function incrementDisposable(): void
    {
        static::incrementCounter('disposable_count');
    }

    /**
     * Increment a specific counter for today's date.
     *
     * Uses insertOrIgnore + increment for atomic operation.
     * insertOrIgnore ensures row exists without overwriting data.
     * increment is atomic at the database level.
     *
     * @param  string  $counter  The counter column to increment
     */
    protected static function incrementCounter(string $counter): void
    {
        $today = now()->format('Y-m-d');

        // Ensure row exists (insertOrIgnore is no-op if row already exists)
        static::query()->insertOrIgnore([
            'date' => $today,
            'valid_count' => 0,
            'invalid_count' => 0,
            'disposable_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Atomic increment
        static::query()
            ->where('date', $today)
            ->increment($counter);
    }

    /**
     * Get statistics for a date range.
     *
     * @return array{total_valid: int, total_invalid: int, total_disposable: int, total_checked: int}
     */
    public static function getStatsForRange(Carbon $from, Carbon $to): array
    {
        $stats = static::query()
            ->forDateRange($from, $to)
            ->selectRaw('
                SUM(valid_count) as total_valid,
                SUM(invalid_count) as total_invalid,
                SUM(disposable_count) as total_disposable
            ')
            ->first();

        $totalValid = (int) ($stats->total_valid ?? 0);
        $totalInvalid = (int) ($stats->total_invalid ?? 0);
        $totalDisposable = (int) ($stats->total_disposable ?? 0);

        return [
            'total_valid' => $totalValid,
            'total_invalid' => $totalInvalid,
            'total_disposable' => $totalDisposable,
            'total_checked' => $totalValid + $totalInvalid + $totalDisposable,
        ];
    }

    /**
     * Delete records older than the specified number of days.
     *
     * @param  int  $days  Number of days to retain (default: 90)
     * @return int Number of records deleted
     */
    public static function pruneOldRecords(int $days = 90): int
    {
        $cutoffDate = now()->subDays($days)->format('Y-m-d');

        return static::query()
            ->where('date', '<', $cutoffDate)
            ->delete();
    }

    /**
     * Get the date of the oldest record.
     */
    public static function getOldestRecordDate(): ?Carbon
    {
        $oldest = static::query()
            ->orderBy('date', 'asc')
            ->first();

        return $oldest?->date;
    }

    /**
     * Get the total number of stat records.
     */
    public static function getRecordCount(): int
    {
        return static::query()->count();
    }
}
