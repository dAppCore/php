<?php

declare(strict_types=1);

namespace Mod\ServiceProviderModule;

use Core\Events\WebRoutesRegistering;
use Illuminate\Support\ServiceProvider;

class Boot extends ServiceProvider
{
    public static array $listens = [
        WebRoutesRegistering::class => 'onWebRoutes',
    ];

    public bool $webRoutesCalled = false;

    public function onWebRoutes(WebRoutesRegistering $event): void
    {
        $this->webRoutesCalled = true;
    }
}
