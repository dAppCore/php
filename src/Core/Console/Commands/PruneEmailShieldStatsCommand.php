<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Console\Commands;

use Core\Mail\EmailShieldStat;
use Illuminate\Console\Command;

/**
 * Prune old Email Shield statistics records.
 *
 * Removes records older than the specified retention period to prevent
 * unbounded table growth. Should be scheduled to run daily.
 *
 * Usage:
 *   php artisan email-shield:prune
 *   php artisan email-shield:prune --days=30
 *
 * Scheduling (in app/Console/Kernel.php):
 *   $schedule->command('email-shield:prune')->daily();
 */
class PruneEmailShieldStatsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'email-shield:prune
                            {--days= : Number of days to retain (default: from config or 90)}
                            {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     */
    protected $description = 'Prune old Email Shield statistics records';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = $this->getRetentionDays();
        $dryRun = $this->option('dry-run');

        $this->newLine();
        $this->components->info('Email Shield Stats Cleanup');
        $this->newLine();

        // Get count of records that would be deleted
        $cutoffDate = now()->subDays($days)->format('Y-m-d');
        $recordsToDelete = EmailShieldStat::query()
            ->where('date', '<', $cutoffDate)
            ->count();

        // Show current state table
        $this->components->twoColumnDetail('<fg=gray;options=bold>Configuration</>', '');
        $this->components->twoColumnDetail('Retention period', "<fg=cyan>{$days} days</>");
        $this->components->twoColumnDetail('Cutoff date', "<fg=cyan>{$cutoffDate}</>");
        $this->components->twoColumnDetail('Records to delete', $recordsToDelete > 0
            ? "<fg=yellow>{$recordsToDelete}</>"
            : '<fg=green>0</>');
        $this->newLine();

        if ($recordsToDelete === 0) {
            $this->components->info('No records older than the retention period found.');
            $this->newLine();

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->components->warn('Dry run mode - no records were deleted.');
            $this->newLine();

            return self::SUCCESS;
        }

        // Show progress for deletion
        $this->components->task(
            "Deleting {$recordsToDelete} old records",
            function () use ($days) {
                EmailShieldStat::pruneOldRecords($days);

                return true;
            }
        );

        $this->newLine();
        $this->components->info("Successfully deleted {$recordsToDelete} records older than {$days} days.");
        $this->newLine();

        // Show remaining stats
        $remaining = EmailShieldStat::getRecordCount();
        $oldest = EmailShieldStat::getOldestRecordDate();

        $this->components->twoColumnDetail('<fg=gray;options=bold>Current State</>', '');
        $this->components->twoColumnDetail('Remaining records', "<fg=cyan>{$remaining}</>");
        if ($oldest) {
            $this->components->twoColumnDetail('Oldest record', "<fg=cyan>{$oldest->format('Y-m-d')}</>");
        }
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Get the retention period in days from option, config, or default.
     */
    protected function getRetentionDays(): int
    {
        // First check command option
        $days = $this->option('days');
        if ($days !== null) {
            return (int) $days;
        }

        // Then check config
        $configDays = config('core.email_shield.retention_days');
        if ($configDays !== null) {
            return (int) $configDays;
        }

        // Default to 90 days
        return 90;
    }

    /**
     * Get shell completion suggestions for options.
     */
    public function complete(
        \Symfony\Component\Console\Completion\CompletionInput $input,
        \Symfony\Component\Console\Completion\CompletionSuggestions $suggestions
    ): void {
        if ($input->mustSuggestOptionValuesFor('days')) {
            // Suggest common retention periods
            $suggestions->suggestValues(['7', '14', '30', '60', '90', '180', '365']);
        }
    }
}
