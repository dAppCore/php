<?php

declare(strict_types=1);

namespace Mod\PrivateListens;

use Core\Events\WebRoutesRegistering;

class Boot
{
    // Private $listens should be ignored
    private static array $listens = [
        WebRoutesRegistering::class => 'onWebRoutes',
    ];

    public function onWebRoutes(WebRoutesRegistering $event): void
    {
        // Should not be called
    }
}
