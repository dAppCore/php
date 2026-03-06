<?php

declare(strict_types=1);

namespace Core\TestCore;

use Core\Events\ConsoleBooting;

class Boot
{
    public static array $listens = [
        ConsoleBooting::class => 'onConsole',
    ];

    public function onConsole(ConsoleBooting $event): void
    {
        // Console initialization
    }
}
