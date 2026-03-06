<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Bouncer\Gate;

use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Action Gate - whitelist-based request authorization.
 *
 * Philosophy: "If it wasn't trained, it doesn't exist."
 *
 * Every controller action must be explicitly permitted. Unknown actions are
 * blocked in production or prompt for approval in training mode.
 *
 * ## Integration Flow
 *
 * ```
 * Request -> ActionGateMiddleware -> Laravel Gate/Policy -> Controller
 * ```
 *
 * ## Configuration
 *
 * See `config/core.php` under the 'bouncer' key for all options.
 */
class Boot extends ServiceProvider
{
    /**
     * Configure action gate middleware.
     *
     * Call this from your application's bootstrap to add the gate to middleware groups.
     *
     * ```php
     * // bootstrap/app.php
     * ->withMiddleware(function (Middleware $middleware) {
     *     \Core\Bouncer\Gate\Boot::middleware($middleware);
     * })
     * ```
     */
    public static function middleware(Middleware $middleware): void
    {
        // Add to specific middleware groups that should be gated
        $guardedGroups = config('core.bouncer.guarded_middleware', ['web', 'admin', 'api', 'client']);

        foreach ($guardedGroups as $group) {
            $middleware->appendToGroup($group, ActionGateMiddleware::class);
        }

        // Register middleware alias for manual use
        $middleware->alias([
            'action.gate' => ActionGateMiddleware::class,
        ]);
    }

    public function register(): void
    {
        // Register as singleton for caching benefits
        $this->app->singleton(ActionGateService::class);

        // Merge config defaults
        $this->mergeConfigFrom(
            dirname(__DIR__, 2).'/config.php',
            'core'
        );
    }

    public function boot(): void
    {
        // Skip if disabled
        if (! config('core.bouncer.enabled', true)) {
            return;
        }

        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/Migrations');

        // Register route macros
        RouteActionMacro::register();

        // Register training/approval routes if in training mode
        if (config('core.bouncer.training_mode', false)) {
            $this->registerTrainingRoutes();
        }
    }

    /**
     * Register routes for training mode approval workflow.
     */
    protected function registerTrainingRoutes(): void
    {
        Route::middleware(['web', 'auth'])
            ->prefix('_bouncer')
            ->name('bouncer.gate.')
            ->group(function () {
                // Approve an action
                Route::post('/approve', function () {
                    $action = request('action');
                    $scope = request('scope');
                    $redirect = request('redirect', '/');

                    if (! $action) {
                        return back()->with('error', 'No action specified');
                    }

                    $guard = request('guard', 'web');
                    $role = request('role');

                    app(ActionGateService::class)->allow(
                        action: $action,
                        guard: $guard,
                        role: $role,
                        scope: $scope,
                        route: request('route'),
                        trainedBy: auth()->id(),
                    );

                    return redirect($redirect)->with('success', "Action '{$action}' has been approved.");
                })->name('approve');

                // List pending actions
                Route::get('/pending', function () {
                    $pending = Models\ActionRequest::pending()
                        ->groupBy('action')
                        ->map(fn ($requests) => [
                            'action' => $requests->first()->action,
                            'count' => $requests->count(),
                            'routes' => $requests->pluck('route')->unique()->values(),
                            'last_at' => $requests->max('created_at'),
                        ])
                        ->values();

                    if (request()->wantsJson()) {
                        return response()->json(['pending' => $pending]);
                    }

                    return view('bouncer::pending', ['pending' => $pending]);
                })->name('pending');
            });
    }
}
