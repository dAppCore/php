<?php

declare(strict_types=1);

namespace Core\Tests\Fixtures\Mod\Scheduled\Actions;

use Core\Actions\Action;
use Core\Actions\Scheduled;

#[Scheduled(frequency: 'dailyAt:09:00', timezone: 'Europe/London', withoutOverlapping: false)]
class DailyAction
{
    use Action;

    public function handle(): string
    {
        return 'daily';
    }
}
