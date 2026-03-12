<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Actions;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

/**
 * Reads scheduled_actions table and wires enabled actions into Laravel's scheduler.
 *
 * This provider runs in console context only. It queries the database for enabled
 * scheduled actions and registers them with the Laravel Schedule facade.
 *
 * The scheduled_actions table is populated by the `schedule:sync` command,
 * which discovers #[Scheduled] attributes on Action classes.
 */
class ScheduleServiceProvider extends ServiceProvider
{
    /**
     * Allowed namespace prefixes — prevents autoloading of classes from unexpected namespaces.
     */
    private const ALLOWED_NAMESPACES = ['App\\', 'Core\\', 'Mod\\'];

    /**
     * Allowed frequency methods — prevents arbitrary method dispatch from DB strings.
     */
    private const ALLOWED_FREQUENCIES = [
        'everyMinute', 'everyTwoMinutes', 'everyThreeMinutes', 'everyFourMinutes',
        'everyFiveMinutes', 'everyTenMinutes', 'everyFifteenMinutes', 'everyThirtyMinutes',
        'hourly', 'hourlyAt', 'everyOddHour', 'everyTwoHours', 'everyThreeHours',
        'everyFourHours', 'everySixHours',
        'daily', 'dailyAt', 'twiceDaily', 'twiceDailyAt',
        'weekly', 'weeklyOn',
        'monthly', 'monthlyOn', 'twiceMonthly', 'lastDayOfMonth',
        'quarterly', 'quarterlyOn',
        'yearly', 'yearlyOn',
        'cron',
    ];

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        // Guard against table not existing (pre-migration)
        try {
            if (! Schema::hasTable('scheduled_actions')) {
                return;
            }
        } catch (\Throwable) {
            // DB unreachable — skip gracefully so scheduler doesn't crash
            return;
        }

        $this->app->booted(function () {
            $this->registerScheduledActions();
        });
    }

    private function registerScheduledActions(): void
    {
        $schedule = $this->app->make(Schedule::class);
        $actions = ScheduledAction::enabled()->get();

        foreach ($actions as $action) {
            try {
                $class = $action->action_class;

                // Validate namespace prefix against allowlist
                $hasAllowedNamespace = false;

                foreach (self::ALLOWED_NAMESPACES as $prefix) {
                    if (str_starts_with($class, $prefix)) {
                        $hasAllowedNamespace = true;

                        break;
                    }
                }

                if (! $hasAllowedNamespace) {
                    logger()->warning("Scheduled action {$class} has disallowed namespace — skipping");

                    continue;
                }

                if (! class_exists($class)) {
                    continue;
                }

                // Verify the class uses the Action trait
                if (! in_array(\Core\Actions\Action::class, class_uses_recursive($class), true)) {
                    logger()->warning("Scheduled action {$class} does not use the Action trait — skipping");

                    continue;
                }

                // Validate frequency method against allowlist
                $method = $action->frequencyMethod();

                if (! in_array($method, self::ALLOWED_FREQUENCIES, true)) {
                    logger()->warning("Scheduled action {$class} has invalid frequency method: {$method}");

                    continue;
                }

                $event = $schedule->call(function () use ($class, $action) {
                    $class::run();
                    $action->markRun();
                })->name($class);

                // Apply frequency
                $args = $action->frequencyArgs();
                $event->{$method}(...$args);

                // Apply options
                if ($action->without_overlapping) {
                    $event->withoutOverlapping();
                }

                if ($action->run_in_background) {
                    $event->runInBackground();
                }

                if ($action->timezone) {
                    $event->timezone($action->timezone);
                }
            } catch (\Throwable $e) {
                logger()->warning("Failed to register scheduled action: {$action->action_class} — {$e->getMessage()}");
            }
        }
    }
}
