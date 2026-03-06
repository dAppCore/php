<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Bouncer\Gate;

use Illuminate\Routing\Route;

/**
 * Route macros for action gate integration.
 *
 * Provides fluent methods for setting action names on routes:
 *
 * ```php
 * Route::post('/products', [ProductController::class, 'store'])
 *     ->action('product.create');
 *
 * Route::delete('/products/{product}', [ProductController::class, 'destroy'])
 *     ->action('product.delete', scope: 'product');
 *
 * Route::get('/public-page', PageController::class)
 *     ->bypassGate();  // Skip action gate entirely
 * ```
 */
class RouteActionMacro
{
    /**
     * Register route macros for action gate.
     */
    public static function register(): void
    {
        /**
         * Set the action name for bouncer gate checking.
         *
         * @param  string  $action  The action identifier (e.g., 'product.create')
         * @param  string|null  $scope  Optional resource scope
         * @return Route
         */
        Route::macro('action', function (string $action, ?string $scope = null): Route {
            /** @var Route $this */
            $this->setAction(array_merge($this->getAction(), [
                'bouncer_action' => $action,
                'bouncer_scope' => $scope,
            ]));

            return $this;
        });

        /**
         * Bypass the action gate for this route.
         *
         * Use sparingly for routes that should never be gated (e.g., login page).
         *
         * @return Route
         */
        Route::macro('bypassGate', function (): Route {
            /** @var Route $this */
            $this->setAction(array_merge($this->getAction(), [
                'bypass_gate' => true,
            ]));

            return $this;
        });

        /**
         * Mark this route as requiring training (explicit pending state).
         *
         * @return Route
         */
        Route::macro('requiresTraining', function (): Route {
            /** @var Route $this */
            $this->setAction(array_merge($this->getAction(), [
                'requires_training' => true,
            ]));

            return $this;
        });
    }
}
