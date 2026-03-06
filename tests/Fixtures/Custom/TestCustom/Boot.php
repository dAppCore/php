<?php

declare(strict_types=1);

namespace App\Custom\TestCustom;

use Core\Events\ApiRoutesRegistering;

class Boot
{
    public static array $listens = [
        ApiRoutesRegistering::class => 'onApi',
    ];

    public function onApi(ApiRoutesRegistering $event): void
    {
        // API initialization
    }
}
