<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Activity\Console;

use Core\Activity\Services\ActivityLogService;
use Illuminate\Console\Command;

/**
 * Command to prune old activity logs.
 *
 * Usage:
 *   php artisan activity:prune           # Use retention from config
 *   php artisan activity:prune --days=30 # Keep last 30 days
 *   php artisan activity:prune --dry-run # Show what would be deleted
 */
class ActivityPruneCommand extends Command
{
    protected $signature = 'activity:prune
                            {--days= : Number of days to retain (default: from config)}
                            {--dry-run : Show count without deleting}';

    protected $description = 'Delete activity logs older than the retention period';

    public function handle(ActivityLogService $activityService): int
    {
        $days = $this->option('days')
            ? (int) $this->option('days')
            : config('core.activity.retention_days', 90);

        if ($days <= 0) {
            $this->warn('Activity pruning is disabled (retention_days = 0).');

            return self::SUCCESS;
        }

        $cutoffDate = now()->subDays($days);

        $this->info("Pruning activities older than {$days} days (before {$cutoffDate->toDateString()})...");

        if ($this->option('dry-run')) {
            // Count without deleting
            $activityModel = config('core.activity.activity_model', \Spatie\Activitylog\Models\Activity::class);
            $count = $activityModel::where('created_at', '<', $cutoffDate)->count();

            $this->info("Would delete {$count} activity records.");

            return self::SUCCESS;
        }

        $deleted = $activityService->prune($days);

        $this->info("Deleted {$deleted} old activity records.");

        return self::SUCCESS;
    }
}
