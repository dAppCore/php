<?php

declare(strict_types=1);

namespace Mod\HighPriority;

use Core\Events\WebRoutesRegistering;

class Boot
{
    public static array $listens = [
        WebRoutesRegistering::class => ['onWebRoutes', 100],
    ];

    public function onWebRoutes(WebRoutesRegistering $event): void
    {
        $event->views('high-priority', __DIR__.'/Views');
    }
}
