<?php

declare(strict_types=1);

namespace Core\Mod\Trees\Console;

use Core\Mod\Trees\Models\TreeDonation;
use Core\Mod\Trees\Models\TreePlanting;
use Core\Mod\Trees\Models\TreeReserve;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Batches confirmed trees into a monthly donation to Trees for the Future.
 *
 * This command:
 * 1. Counts all confirmed trees not yet in a donation batch
 * 2. Creates a TreeDonation record with batch reference
 * 3. Marks all included trees as 'planted' with the batch reference
 * 4. Replenishes the tree reserve
 *
 * Run monthly on the 28th or when reserve falls below threshold.
 */
class DonateTreesToTFTF extends Command
{
    protected $signature = 'trees:donate
                            {--dry-run : Show what would be donated without actually donating}
                            {--replenish= : Number of trees to replenish the reserve (from actual TFTF donation)}';

    protected $description = 'Batch confirmed trees into monthly TFTF donation';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $replenishCount = $this->option('replenish');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        // Get all confirmed trees not yet in a batch
        $confirmedTrees = TreePlanting::confirmed()
            ->whereNull('tftf_reference')
            ->get();

        $totalTrees = $confirmedTrees->sum('trees');

        $this->info('Trees for the Future - Monthly Donation Batch');
        $this->newLine();

        if ($totalTrees === 0) {
            $this->info('No confirmed trees awaiting batch donation.');

            // Handle manual reserve replenishment
            if ($replenishCount) {
                return $this->handleReplenishment((int) $replenishCount, $dryRun);
            }

            return self::SUCCESS;
        }

        $this->table(
            ['Metric', 'Value'],
            [
                ['Confirmed trees', $confirmedTrees->count().' records'],
                ['Total trees', number_format($totalTrees)],
                ['Estimated cost', '$'.number_format($totalTrees * TreeDonation::COST_PER_TREE, 2)],
            ]
        );

        $this->newLine();

        // Generate batch reference
        $batchReference = 'TFTF-'.now()->format('Ymd').'-'.strtoupper(substr(md5(uniqid()), 0, 6));

        if ($dryRun) {
            $this->info("Would create donation batch: {$batchReference}");
            $this->info("Would mark {$confirmedTrees->count()} plantings as planted");
            $this->newLine();
            $this->warn('DRY RUN COMPLETE - No changes made');

            return self::SUCCESS;
        }

        // Create the donation record
        $donation = TreeDonation::createBatch($totalTrees, $batchReference);

        $this->info("Created donation batch: {$donation->batch_reference}");

        // Mark all confirmed trees as planted
        $updated = 0;
        foreach ($confirmedTrees as $planting) {
            $planting->markPlanted($batchReference);
            $updated++;
        }

        Log::info('Monthly tree donation batch created', [
            'batch_reference' => $batchReference,
            'total_trees' => $totalTrees,
            'plantings_updated' => $updated,
            'amount' => $donation->amount,
        ]);

        $this->info("Marked {$updated} plantings as planted");

        // Handle replenishment if specified
        if ($replenishCount) {
            $this->handleReplenishment((int) $replenishCount, $dryRun);
        }

        $this->newLine();
        $this->info('Next steps:');
        $this->line('1. Make donation at: https://donate.trees.org/-/NPMMSVUP?member=SWZTDDWH');
        $this->line("2. Run: php artisan trees:reserve:add {$totalTrees}");
        $this->line('   (After donation is confirmed)');

        return self::SUCCESS;
    }

    /**
     * Handle reserve replenishment.
     */
    protected function handleReplenishment(int $count, bool $dryRun): int
    {
        $this->newLine();
        $this->info("Replenishing tree reserve with {$count} trees...");

        if ($dryRun) {
            $currentReserve = TreeReserve::current();
            $this->info("Would update reserve: {$currentReserve} -> ".($currentReserve + $count));

            return self::SUCCESS;
        }

        TreeReserve::replenish($count);

        $this->info('Reserve replenished. New total: '.TreeReserve::current());

        return self::SUCCESS;
    }
}
