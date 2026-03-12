<?php

declare(strict_types=1);

namespace Core\Tests\Fixtures\Mod\Scheduled\Actions;

use Core\Actions\Action;

class NotScheduledAction
{
    use Action;

    public function handle(): string
    {
        return 'not scheduled';
    }
}
