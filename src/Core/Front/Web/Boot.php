<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Front\Web;

use Core\Front\Web\Middleware\FindDomainRecord;
use Core\Front\Web\Middleware\ResilientSession;
use Core\Headers\SecurityHeaders;
use Core\LifecycleEventProvider;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

/**
 * Web frontage - public marketing stage.
 *
 * Provides web middleware group for public-facing pages.
 * Apps can extend this to add custom middleware.
 */
class Boot extends ServiceProvider
{
    /**
     * Configure web middleware group.
     */
    public static function middleware(Middleware $middleware): void
    {
        $middleware->group('web', [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            ResilientSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            SecurityHeaders::class,
            FindDomainRecord::class,
        ]);
    }

    /**
     * Register services.
     */
    public function register(): void
    {
        // Alias 'admin' to Livewire manager for Flux package compatibility
        // The Flux fork uses app('admin') where standard Flux uses app('livewire')
        $this->app->alias('livewire', 'admin');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register web:: namespace for public workspace pages (home, waitlist, page)
        $this->loadViewsFrom(dirname(__DIR__).'/Components/View/Blade/web', 'web');

        // Register web:: anonymous components
        Blade::anonymousComponentPath(__DIR__.'/Blade', 'web');
        Blade::anonymousComponentPath(__DIR__.'/Blade/components', 'web');

        // Register <web:xyz> tag compiler (like <flux:xyz>)
        $this->bootTagCompiler();

        // Fire WebRoutesRegistering after app is booted (Livewire routes need to exist first)
        $this->app->booted(function () {
            LifecycleEventProvider::fireWebRoutes();
        });
    }

    /**
     * Register the custom <web:xyz> tag compiler.
     */
    protected function bootTagCompiler(): void
    {
        $compiler = new WebTagCompiler(
            app('blade.compiler')->getClassComponentAliases(),
            app('blade.compiler')->getClassComponentNamespaces(),
            app('blade.compiler')
        );

        app('blade.compiler')->precompiler(function (string $value) use ($compiler) {
            return $compiler->compile($value);
        });
    }
}
