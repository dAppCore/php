<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

use Core\Front\Client\View\Dashboard;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Client Routes
|--------------------------------------------------------------------------
|
| Routes for namespace owners managing their personal workspace.
| Uses 'client' middleware group (authenticated, namespace owner).
|
*/

Route::middleware('client')->group(function () {
    // Dashboard
    Route::get('/dashboard', Dashboard::class)->name('client.dashboard');

    // Additional routes can be registered via ClientRoutesRegistering event
});
