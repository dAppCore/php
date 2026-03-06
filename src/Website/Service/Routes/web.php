<?php

declare(strict_types=1);

use Core\Website\Service\View\Features;
use Core\Website\Service\View\Landing;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Service Mod Routes
|--------------------------------------------------------------------------
|
| Generic marketing/landing pages for services.
| Uses workspace data for dynamic theming based on service color.
|
*/

Route::get('/', Landing::class)->name('service.home');
Route::get('/features', Features::class)->name('service.features');
