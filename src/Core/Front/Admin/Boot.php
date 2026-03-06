<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Front\Admin;

use Core\Front\Admin\View\Components\ActivityFeed;
use Core\Front\Admin\View\Components\ActivityLog;
use Core\Front\Admin\View\Components\Alert;
use Core\Front\Admin\View\Components\CardGrid;
use Core\Front\Admin\View\Components\ClearFilters;
use Core\Front\Admin\View\Components\DataTable;
use Core\Front\Admin\View\Components\EditableTable;
use Core\Front\Admin\View\Components\Filter;
use Core\Front\Admin\View\Components\FilterBar;
use Core\Front\Admin\View\Components\LinkGrid;
use Core\Front\Admin\View\Components\ManagerTable;
use Core\Front\Admin\View\Components\Metrics;
use Core\Front\Admin\View\Components\ProgressList;
use Core\Front\Admin\View\Components\Search;
use Core\Front\Admin\View\Components\ServiceCard;
use Core\Front\Admin\View\Components\Sidemenu;
use Core\Front\Admin\View\Components\Stats;
use Core\Front\Admin\View\Components\StatusCards;
use Core\Headers\SecurityHeaders;
use Core\LifecycleEventProvider;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

/**
 * Admin frontage - admin dashboard stage.
 *
 * Provides admin:: namespace and admin middleware group.
 * Does NOT inherit from web - completely separate stack.
 */
class Boot extends ServiceProvider
{
    /**
     * Configure admin middleware group.
     */
    public static function middleware(Middleware $middleware): void
    {
        $middleware->group('admin', [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            SecurityHeaders::class,
            'auth',
        ]);
    }

    public function register(): void
    {
        $this->app->singleton(AdminMenuRegistry::class);
    }

    public function boot(): void
    {
        // Register admin:: namespace for admin shell components
        $this->loadViewsFrom(__DIR__.'/Blade', 'admin');
        Blade::anonymousComponentPath(__DIR__.'/Blade', 'admin');

        // Register class-backed components
        Blade::component('admin-activity-feed', ActivityFeed::class);
        Blade::component('admin-activity-log', ActivityLog::class);
        Blade::component('admin-alert', Alert::class);
        Blade::component('admin-card-grid', CardGrid::class);
        Blade::component('admin-clear-filters', ClearFilters::class);
        Blade::component('admin-data-table', DataTable::class);
        Blade::component('admin-editable-table', EditableTable::class);
        Blade::component('admin-filter', Filter::class);
        Blade::component('admin-filter-bar', FilterBar::class);
        Blade::component('admin-link-grid', LinkGrid::class);
        Blade::component('admin-manager-table', ManagerTable::class);
        Blade::component('admin-metrics', Metrics::class);
        Blade::component('admin-progress-list', ProgressList::class);
        Blade::component('admin-search', Search::class);
        Blade::component('admin-service-card', ServiceCard::class);
        Blade::component('admin-sidemenu', Sidemenu::class);
        Blade::component('admin-stats', Stats::class);
        Blade::component('admin-status-cards', StatusCards::class);

        // Register <admin:xyz> tag compiler (like <flux:xyz>)
        $this->bootTagCompiler();

        // Fire AdminPanelBooting event for lazy-loaded modules
        LifecycleEventProvider::fireAdminBooting();
    }

    /**
     * Register the custom <admin:xyz> tag compiler.
     */
    protected function bootTagCompiler(): void
    {
        $compiler = new AdminTagCompiler(
            app('blade.compiler')->getClassComponentAliases(),
            app('blade.compiler')->getClassComponentNamespaces(),
            app('blade.compiler')
        );

        app('blade.compiler')->precompiler(function (string $value) use ($compiler) {
            return $compiler->compile($value);
        });
    }
}
