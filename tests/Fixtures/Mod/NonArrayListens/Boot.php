<?php

declare(strict_types=1);

namespace Mod\NonArrayListens;

class Boot
{
    // Non-array $listens should be ignored
    public static string $listens = 'not-an-array';

    public function handle(): void
    {
        // Does nothing
    }
}
