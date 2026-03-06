<?php

declare(strict_types=1);

namespace Plug\TestPlugin;

use Core\Events\FrameworkBooted;

class Boot
{
    public static array $listens = [
        FrameworkBooted::class => 'onBooted',
    ];

    public function onBooted(FrameworkBooted $event): void
    {
        // Plugin initialization
    }
}
