<?php

declare(strict_types=1);

/*
 * A Boot.php whose namespace does not match its path.
 *
 * It sits under a `/Mod` directory, so the old path convention derived
 * `Mod\Displaced\Boot` for it. It declares something else entirely, which is
 * the only thing that is actually true about it. Real packages do this
 * constantly — src/Mod/Trees declares Core\Mod\Trees, php-commerce keeps
 * Core\Service\Commerce under Service/ — and a scanner that guesses instead of
 * reading gets every one of them wrong.
 */

namespace Core\Tests\Fixtures\Mod\Displaced;

use Core\Events\WebRoutesRegistering;

class Boot
{
    public static array $listens = [
        WebRoutesRegistering::class => 'onWebRoutes',
    ];

    public function onWebRoutes(WebRoutesRegistering $event): void
    {
        $event->views('displaced', __DIR__.'/Views');
    }
}
