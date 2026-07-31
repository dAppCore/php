<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Console;

use Core\Console\Commands\InstallCommand;
use Core\Console\Commands\MakeModCommand;
use Core\Console\Commands\MakePlugCommand;
use Core\Console\Commands\MakeWebsiteCommand;
use Core\Console\Commands\NewProjectCommand;
use Core\Console\Commands\PruneEmailShieldStatsCommand;
use Core\Console\Commands\ScheduleSyncCommand;
use Core\Events\ConsoleBooting;

/**
 * Core Console module - registers framework artisan commands.
 */
class Boot
{
    public static array $listens = [
        ConsoleBooting::class => 'onConsole',
    ];

    public function onConsole(ConsoleBooting $event): void
    {
        $event->command(InstallCommand::class);
        $event->command(NewProjectCommand::class);
        $event->command(MakeModCommand::class);
        $event->command(MakePlugCommand::class);
        $event->command(MakeWebsiteCommand::class);
        $event->command(PruneEmailShieldStatsCommand::class);
        $event->command(ScheduleSyncCommand::class);
    }
}
