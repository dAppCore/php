<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Front\Components;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

/**
 * Core front-end components and layouts.
 *
 * Namespaces:
 *   core::      <core:icon> tag syntax + <x-core::xyz>
 *   layouts::   Livewire layouts (->layout('layouts::app'))
 *   front::     Front-end components (<x-front::satellite.layout>)
 *   errors::    Error pages (404, 500, 503)
 */
class Boot extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $blade = __DIR__.'/View/Blade';

        // Add to view paths for Livewire's layout resolution
        // Makes ->layout('layouts.app') find layouts/app.blade.php
        $this->app['view']->addLocation($blade);

        // Register core:: namespace (<core:icon> + <x-core::xyz>)
        $this->loadViewsFrom($blade, 'core');
        Blade::anonymousComponentPath($blade, 'core');

        // Register layouts:: namespace
        $this->loadViewsFrom($blade.'/layouts', 'layouts');
        Blade::anonymousComponentPath($blade.'/layouts', 'layouts');

        // Register front:: namespace for front-end components
        Blade::anonymousComponentPath($blade.'/components', 'front');

        // Register error views
        $this->loadViewsFrom($blade.'/errors', 'errors');

        // Register <core:xyz> tag compiler (like <flux:xyz>)
        $this->bootTagCompiler();
    }

    /**
     * Register the custom <core:xyz> tag compiler.
     */
    protected function bootTagCompiler(): void
    {
        $compiler = new CoreTagCompiler(
            app('blade.compiler')->getClassComponentAliases(),
            app('blade.compiler')->getClassComponentNamespaces(),
            app('blade.compiler')
        );

        app('blade.compiler')->precompiler(function (string $value) use ($compiler) {
            return $compiler->compile($value);
        });
    }
}
