<?php

declare(strict_types=1);

use Core\Mod\Trees\Controllers\Api\TreeStatsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Trees API Routes
|--------------------------------------------------------------------------
|
| Public API for Trees for Agents statistics. No authentication required.
|
*/

Route::middleware('throttle:60,1')->prefix('trees')->group(function () {
    Route::get('/stats', [TreeStatsController::class, 'index'])
        ->name('api.trees.stats');
    Route::get('/stats/{provider}', [TreeStatsController::class, 'provider'])
        ->name('api.trees.stats.provider');
    Route::get('/stats/{provider}/{model}', [TreeStatsController::class, 'model'])
        ->name('api.trees.stats.model');
    Route::get('/leaderboard', [TreeStatsController::class, 'leaderboard'])
        ->name('api.trees.leaderboard');
});
