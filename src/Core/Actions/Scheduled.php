<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Actions;

use Attribute;

/**
 * Mark an Action class for scheduled execution.
 *
 * The frequency string maps to Laravel Schedule methods:
 * - 'everyMinute' → ->everyMinute()
 * - 'dailyAt:09:00' → ->dailyAt('09:00')
 * - 'weeklyOn:1,09:00' → ->weeklyOn(1, '09:00')
 * - 'hourly' → ->hourly()
 * - 'monthlyOn:1,00:00' → ->monthlyOn(1, '00:00')
 *
 * Usage:
 *   #[Scheduled(frequency: 'dailyAt:09:00', timezone: 'Europe/London')]
 *   class PublishDigest
 *   {
 *       use Action;
 *       public function handle(): void { ... }
 *   }
 *
 * Discovered by ScheduledActionScanner, persisted to scheduled_actions table
 * via `php artisan schedule:sync`, and executed by ScheduleServiceProvider.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Scheduled
{
    public function __construct(
        public string $frequency,
        public ?string $timezone = null,
        public bool $withoutOverlapping = true,
        public bool $runInBackground = true,
    ) {}
}
