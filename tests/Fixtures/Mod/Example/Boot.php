<?php

declare(strict_types=1);

namespace Mod\Example;

use Core\Events\WebRoutesRegistering;

class Boot
{
    public static array $listens = [
        WebRoutesRegistering::class => 'onWebRoutes',
    ];

    public function onWebRoutes(WebRoutesRegistering $event): void
    {
        $event->views('example', __DIR__.'/Views');
    }
}
