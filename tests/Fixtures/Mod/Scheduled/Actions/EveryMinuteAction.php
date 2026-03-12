<?php

declare(strict_types=1);

namespace Core\Tests\Fixtures\Mod\Scheduled\Actions;

use Core\Actions\Action;
use Core\Actions\Scheduled;

#[Scheduled(frequency: 'everyMinute')]
class EveryMinuteAction
{
    use Action;

    public function handle(): string
    {
        return 'ran';
    }
}
