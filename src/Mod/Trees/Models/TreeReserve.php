<?php

declare(strict_types=1);

namespace Core\Mod\Trees\Models;

use Core\Mod\Trees\Notifications\LowTreeReserveNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Manages the pre-paid tree reserve for Trees for Agents.
 *
 * This is a single-row table that tracks:
 * - Current reserve level
 * - Total trees decremented (on confirmation)
 * - Total trees replenished (on batch donation)
 *
 * Warning threshold: 50 trees
 */
class TreeReserve extends Model
{
    /**
     * Warning threshold - send notification when reserve falls below this.
     */
    public const WARNING_THRESHOLD = 50;

    /**
     * Critical threshold - send urgent notification.
     */
    public const CRITICAL_THRESHOLD = 10;

    protected $fillable = [
        'reserve',
        'initial_reserve',
        'total_decremented',
        'total_replenished',
        'last_decremented_at',
        'last_replenished_at',
    ];

    protected $casts = [
        'reserve' => 'integer',
        'initial_reserve' => 'integer',
        'total_decremented' => 'integer',
        'total_replenished' => 'integer',
        'last_decremented_at' => 'datetime',
        'last_replenished_at' => 'datetime',
    ];

    /**
     * Get the singleton reserve instance.
     */
    public static function instance(): self
    {
        return static::firstOrCreate(
            ['id' => 1],
            [
                'reserve' => 695,
                'initial_reserve' => 695,
                'total_decremented' => 0,
                'total_replenished' => 0,
            ]
        );
    }

    /**
     * Get the current reserve level.
     */
    public static function current(): int
    {
        return static::instance()->reserve;
    }

    /**
     * Check if reserve has trees available.
     */
    public static function hasAvailable(int $trees = 1): bool
    {
        return static::current() >= $trees;
    }

    /**
     * Decrement the reserve when trees are confirmed.
     *
     * @return bool True if decremented, false if insufficient reserve
     */
    public static function decrementReserve(int $trees = 1): bool
    {
        $reserve = static::instance();

        if ($reserve->reserve < $trees) {
            Log::warning('Tree reserve insufficient', [
                'requested' => $trees,
                'available' => $reserve->reserve,
            ]);

            return false;
        }

        $reserve->reserve -= $trees;
        $reserve->total_decremented += $trees;
        $reserve->last_decremented_at = now();
        $reserve->save();

        // Check for low reserve warning
        static::checkAndNotifyLowReserve($reserve);

        Log::info('Tree reserve decremented', [
            'trees' => $trees,
            'remaining' => $reserve->reserve,
        ]);

        return true;
    }

    /**
     * Replenish the reserve after a batch donation.
     */
    public static function replenish(int $trees): void
    {
        $reserve = static::instance();

        $reserve->reserve += $trees;
        $reserve->total_replenished += $trees;
        $reserve->last_replenished_at = now();
        $reserve->save();

        Log::info('Tree reserve replenished', [
            'trees' => $trees,
            'new_total' => $reserve->reserve,
        ]);
    }

    /**
     * Check reserve level and send notifications if needed.
     */
    protected static function checkAndNotifyLowReserve(self $reserve): void
    {
        if ($reserve->reserve <= 0) {
            static::sendLowReserveNotification('depleted', $reserve->reserve);
        } elseif ($reserve->reserve < self::CRITICAL_THRESHOLD) {
            static::sendLowReserveNotification('critical', $reserve->reserve);
        } elseif ($reserve->reserve < self::WARNING_THRESHOLD) {
            static::sendLowReserveNotification('warning', $reserve->reserve);
        }
    }

    /**
     * Send low reserve notification to admins.
     */
    protected static function sendLowReserveNotification(string $level, int $remaining): void
    {
        // Get admin notification email from config
        $adminEmail = config('mail.admin_address', 'admin@host.uk.com');

        Log::warning('Low tree reserve notification', [
            'level' => $level,
            'remaining' => $remaining,
        ]);

        try {
            Notification::route('mail', $adminEmail)
                ->notify(new LowTreeReserveNotification($level, $remaining));
        } catch (\Throwable $e) {
            Log::error('Failed to send low tree reserve notification', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get reserve statistics.
     */
    public static function stats(): array
    {
        $reserve = static::instance();

        return [
            'current_reserve' => $reserve->reserve,
            'initial_reserve' => $reserve->initial_reserve,
            'total_decremented' => $reserve->total_decremented,
            'total_replenished' => $reserve->total_replenished,
            'last_decremented_at' => $reserve->last_decremented_at?->toIso8601String(),
            'last_replenished_at' => $reserve->last_replenished_at?->toIso8601String(),
            'status' => static::getStatus($reserve->reserve),
        ];
    }

    /**
     * Get human-readable status based on reserve level.
     */
    protected static function getStatus(int $reserve): string
    {
        if ($reserve <= 0) {
            return 'depleted';
        }

        if ($reserve < self::CRITICAL_THRESHOLD) {
            return 'critical';
        }

        if ($reserve < self::WARNING_THRESHOLD) {
            return 'warning';
        }

        return 'healthy';
    }
}
