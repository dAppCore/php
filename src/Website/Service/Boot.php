<?php

declare(strict_types=1);

namespace Core\Website\Service;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Generic Service Mod.
 *
 * Serves the public marketing/landing pages for services.
 * Uses workspace data (name, icon, color) for dynamic theming.
 *
 * Services can override this by having their own Mod/{Service} module.
 */
class Boot extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        View::addNamespace('service', __DIR__.'/View/Blade');

        Route::middleware('web')
            ->domain(request()->getHost())
            ->group(__DIR__.'/Routes/web.php');
    }
}
