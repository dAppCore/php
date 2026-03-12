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
    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        // Guard against table not existing (pre-migration)
        if (! Schema::hasTable('scheduled_actions')) {
            return;
        }

        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);

            $actions = ScheduledAction::enabled()->get();

            foreach ($actions as $action) {
                $class = $action->action_class;

                if (! class_exists($class)) {
                    continue;
                }

                $event = $schedule->call(function () use ($class, $action) {
                    $class::run();
                    $action->markRun();
                })->name($class);

                // Apply frequency
                $method = $action->frequencyMethod();
                $args = $action->frequencyArgs();
                $event->{$method}(...$args);

                // Apply options
                if ($action->without_overlapping) {
                    $event->withoutOverlapping();
                }

                if ($action->timezone) {
                    $event->timezone($action->timezone);
                }
            }
        });
    }
}
