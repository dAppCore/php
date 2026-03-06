<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

use Core\Config\View\Modal\Admin\ConfigPanel;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'admin'])
    ->prefix('admin')
    ->group(function () {
        Route::get('/config', ConfigPanel::class)->name('admin.config');
    });
