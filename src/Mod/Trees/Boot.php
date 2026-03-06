<?php

declare(strict_types=1);

namespace Core\Mod\Trees;

use Core\Events\ApiRoutesRegistering;
use Core\Events\ConsoleBooting;
use Core\Events\WebRoutesRegistering;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class Boot extends ServiceProvider
{
    protected string $moduleName = 'trees';

    /**
     * Events this module listens to for lazy loading.
     *
     * @var array<class-string, string>
     */
    public static array $listens = [
        ApiRoutesRegistering::class => 'onApiRoutes',
        WebRoutesRegistering::class => 'onWebRoutes',
        ConsoleBooting::class => 'onConsole',
    ];

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/Lang/en_GB', 'trees');
    }

    // -------------------------------------------------------------------------
    // Event-driven handlers
    // -------------------------------------------------------------------------

    public function onApiRoutes(ApiRoutesRegistering $event): void
    {
        if (file_exists(__DIR__.'/Routes/api.php')) {
            $event->routes(fn () => Route::middleware('api')->group(__DIR__.'/Routes/api.php'));
        }
    }

    public function onWebRoutes(WebRoutesRegistering $event): void
    {
        $event->views($this->moduleName, __DIR__.'/View/Blade');

        if (file_exists(__DIR__.'/Routes/web.php')) {
            $event->routes(fn () => require __DIR__.'/Routes/web.php');
        }

        $event->livewire('trees.index', View\Modal\Web\Index::class);
    }

    public function onConsole(ConsoleBooting $event): void
    {
        $event->command(Console\ProcessQueuedTrees::class);
        $event->command(Console\DonateTreesToTFTF::class);
        $event->command(Console\AddTreeReserve::class);
    }
}
