<?php

use Core\Mod\Trees\View\Modal\Web\Index as TreesIndex;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Trees Routes
|--------------------------------------------------------------------------
*/

// Trees for Agents public leaderboard
Route::middleware(['web'])->group(function () {
    Route::get('/trees', TreesIndex::class)->name('trees');

    // Agent referral tracking (Trees for Agents)
    Route::get('/ref/{provider}/{model?}', [\Core\Tenant\Controllers\ReferralController::class, 'track'])
        ->name('referral.agent');
});
