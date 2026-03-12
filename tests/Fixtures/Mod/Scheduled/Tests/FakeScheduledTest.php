<?php

declare(strict_types=1);

namespace Core\Tests\Fixtures\Mod\Scheduled\Tests;

use Core\Actions\Action;
use Core\Actions\Scheduled;

/**
 * This file lives inside a Tests/ directory to verify that the
 * scanner skips test directories. Despite having #[Scheduled],
 * it should never be discovered.
 */
#[Scheduled(frequency: 'everyMinute')]
class FakeScheduledTest
{
    use Action;

    public function handle(): string
    {
        return 'should-not-be-discovered';
    }
}
