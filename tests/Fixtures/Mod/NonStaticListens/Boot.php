<?php

declare(strict_types=1);

namespace Mod\NonStaticListens;

use Core\Events\WebRoutesRegistering;

class Boot
{
    // Non-static $listens should be ignored
    public array $listens = [
        WebRoutesRegistering::class => 'onWebRoutes',
    ];

    public function onWebRoutes(WebRoutesRegistering $event): void
    {
        // Should not be called
    }
}
