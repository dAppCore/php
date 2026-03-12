<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Console\Commands;

use Core\Actions\ScheduledAction;
use Core\Actions\ScheduledActionScanner;
use Illuminate\Console\Command;

/**
 * Sync #[Scheduled] attribute declarations to the database.
 *
 * Scans configured paths for Action classes with the #[Scheduled] attribute
 * and upserts them into the scheduled_actions table. Run during deploy/migration.
 */
class ScheduleSyncCommand extends Command
{
    protected $signature = 'schedule:sync';

    protected $description = 'Sync #[Scheduled] action attributes to the database';

    public function handle(ScheduledActionScanner $scanner): int
    {
        $paths = config('core.scheduled_action_paths');

        if ($paths === null) {
            $paths = [
                app_path('Core'),
                app_path('Mod'),
                app_path('Website'),
            ];

            // Also scan framework paths
            $frameworkSrc = dirname(__DIR__, 3);
            $paths[] = $frameworkSrc.'/Core';
            $paths[] = $frameworkSrc.'/Mod';
        }

        $discovered = $scanner->scan($paths);

        $added = 0;
        $disabled = 0;
        $unchanged = 0;

        // Upsert discovered actions
        foreach ($discovered as $class => $attribute) {
            $existing = ScheduledAction::where('action_class', $class)->first();

            if ($existing) {
                $unchanged++;

                continue;
            }

            ScheduledAction::create([
                'action_class' => $class,
                'frequency' => $attribute->frequency,
                'timezone' => $attribute->timezone,
                'without_overlapping' => $attribute->withoutOverlapping,
                'run_in_background' => $attribute->runInBackground,
                'is_enabled' => true,
            ]);

            $added++;
        }

        // Disable actions no longer in codebase
        $discoveredClasses = array_keys($discovered);
        $stale = ScheduledAction::where('is_enabled', true)
            ->whereNotIn('action_class', $discoveredClasses)
            ->get();

        foreach ($stale as $action) {
            $action->update(['is_enabled' => false]);
            $disabled++;
        }

        $this->info("Schedule sync complete: {$added} added, {$disabled} disabled, {$unchanged} unchanged.");

        return Command::SUCCESS;
    }
}
