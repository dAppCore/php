<?php

declare(strict_types=1);

namespace Core\Mod\Trees\Console;

use Core\Mod\Trees\Models\TreePlanting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Process the oldest queued tree planting.
 *
 * Part of the Trees for Agents programme. This command:
 * 1. Finds the oldest queued tree planting
 * 2. Changes its status to pending
 * 3. Confirms the planting (updates stats)
 *
 * Run daily at midnight to ensure 1 tree/day from the queue.
 */
class ProcessQueuedTrees extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'trees:process-queue
                            {--dry-run : Show what would be processed without making changes}';

    /**
     * The console command description.
     */
    protected $description = 'Process the oldest queued tree planting (runs daily)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        // Get the oldest queued tree
        $planting = TreePlanting::oldestQueued();

        if (! $planting) {
            $this->info('No queued trees to process.');

            return Command::SUCCESS;
        }

        $this->info('Found queued tree:');
        $this->table(
            ['ID', 'Provider', 'Model', 'User ID', 'Created At'],
            [[
                $planting->id,
                $planting->provider ?? 'unknown',
                $planting->model ?? '—',
                $planting->user_id ?? '—',
                $planting->created_at->toDateTimeString(),
            ]]
        );

        if ($dryRun) {
            $this->warn('Dry run — no changes made.');

            return Command::SUCCESS;
        }

        // Update status to pending, then confirm
        $planting->update(['status' => TreePlanting::STATUS_PENDING]);
        $planting->markConfirmed();

        Log::info('Processed queued tree planting', [
            'tree_planting_id' => $planting->id,
            'provider' => $planting->provider,
            'model' => $planting->model,
        ]);

        $this->info("Tree #{$planting->id} has been confirmed.");

        // Show remaining queue
        $remaining = TreePlanting::queued()->count();
        $this->info("Remaining in queue: {$remaining} trees");

        return Command::SUCCESS;
    }
}
