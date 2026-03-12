<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Webhook;

use Core\Events\ApiRoutesRegistering;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class Boot extends ServiceProvider
{
    public static array $listens = [
        ApiRoutesRegistering::class => 'onApiRoutes',
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config.php', 'webhook');
    }

    public function onApiRoutes(ApiRoutesRegistering $event): void
    {
        $event->routes(fn () => Route::post(
            '/webhooks/{source}',
            [WebhookController::class, 'handle']
        )->where('source', '[a-z0-9\-]+'));
    }
}
