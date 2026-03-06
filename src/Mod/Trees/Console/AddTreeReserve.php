<?php

declare(strict_types=1);

namespace Core\Mod\Trees\Console;

use Core\Mod\Trees\Models\TreeReserve;
use Illuminate\Console\Command;

/**
 * Manually add trees to the reserve after a TFTF donation.
 *
 * Use this command after making a donation to Trees for the Future
 * to replenish the pre-paid tree reserve.
 */
class AddTreeReserve extends Command
{
    protected $signature = 'trees:reserve:add
                            {count : Number of trees to add to the reserve}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Add trees to the reserve after a TFTF donation';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $count = (int) $this->argument('count');

        if ($count <= 0) {
            $this->error('Tree count must be a positive number.');

            return self::FAILURE;
        }

        $currentReserve = TreeReserve::current();
        $stats = TreeReserve::stats();

        $this->info('Current Tree Reserve Status');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Current reserve', number_format($currentReserve)],
                ['Initial reserve', number_format($stats['initial_reserve'])],
                ['Total decremented', number_format($stats['total_decremented'])],
                ['Total replenished', number_format($stats['total_replenished'])],
                ['Status', $stats['status']],
            ]
        );

        $this->newLine();
        $this->info("Adding {$count} trees will set reserve to: ".($currentReserve + $count));

        if (! $this->option('force')) {
            if (! $this->confirm('Proceed with adding trees to the reserve?')) {
                $this->info('Operation cancelled.');

                return self::SUCCESS;
            }
        }

        TreeReserve::replenish($count);

        $this->newLine();
        $this->info("Successfully added {$count} trees to the reserve.");
        $this->info('New reserve total: '.TreeReserve::current());

        return self::SUCCESS;
    }
}
