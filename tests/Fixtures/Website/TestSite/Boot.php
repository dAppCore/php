<?php

declare(strict_types=1);

namespace Website\TestSite;

use Core\Events\WebRoutesRegistering;

class Boot
{
    public static array $listens = [
        WebRoutesRegistering::class => 'onWebRoutes',
    ];

    public function onWebRoutes(WebRoutesRegistering $event): void
    {
        $event->views('test-site', __DIR__.'/Views');
    }
}
