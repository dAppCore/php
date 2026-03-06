<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Seo\Console\Commands;

use Core\Seo\Analytics\SeoScoreTrend;
use Illuminate\Console\Command;

/**
 * Records SEO scores for trend tracking.
 *
 * Run this command periodically (e.g., daily via cron) to track
 * SEO score changes over time.
 *
 * Usage:
 *   php artisan seo:record-scores
 *   php artisan seo:record-scores --prune
 *   php artisan seo:record-scores --prune --retention=60
 */
class RecordSeoScores extends Command
{
    /**
     * Command signature.
     *
     * @var string
     */
    protected $signature = 'seo:record-scores
                            {--prune : Prune old records after recording}
                            {--retention=90 : Days to retain when pruning}
                            {--force : Force recording even if within minimum interval}';

    /**
     * Command description.
     *
     * @var string
     */
    protected $description = 'Record current SEO scores for trend tracking';

    /**
     * Execute the command.
     */
    public function handle(SeoScoreTrend $trend): int
    {
        if (! $trend->isEnabled()) {
            $this->components->error('SEO score trend tracking is not enabled or table does not exist.');

            return self::FAILURE;
        }

        $this->components->info('Recording SEO scores...');

        $count = $trend->recordAllScores();

        $this->components->twoColumnDetail('Scores recorded', (string) $count);

        if ($this->option('prune')) {
            $retention = (int) $this->option('retention');

            $this->components->info("Pruning records older than {$retention} days...");

            $pruned = $trend->prune($retention);

            $this->components->twoColumnDetail('Records pruned', (string) $pruned);
        }

        // Show summary statistics
        $this->newLine();
        $stats = $trend->getSiteStats();

        $this->components->info('Site SEO Statistics');
        $this->components->twoColumnDetail('Average score', (string) $stats['current_avg']);
        $this->components->twoColumnDetail('Change from last period', $this->formatChange($stats['change']));
        $this->components->twoColumnDetail('Total pages tracked', (string) $stats['total_pages']);
        $this->components->twoColumnDetail('Improving', (string) $stats['improving']);
        $this->components->twoColumnDetail('Declining', (string) $stats['declining']);
        $this->components->twoColumnDetail('Stable', (string) $stats['stable']);

        return self::SUCCESS;
    }

    /**
     * Format score change for display.
     */
    protected function formatChange(float $change): string
    {
        if ($change > 0) {
            return '<fg=green>+'.$change.'</>';
        }

        if ($change < 0) {
            return '<fg=red>'.$change.'</>';
        }

        return '0 (no change)';
    }
}
