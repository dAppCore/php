<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

use Core\Media\Thumbnail\ThumbnailController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Media Web Routes
|--------------------------------------------------------------------------
|
| These routes handle media-related HTTP requests such as lazy thumbnail
| generation. The thumbnail route serves images on-demand, generating
| them when first requested.
|
*/

Route::get('/media/thumb', [ThumbnailController::class, 'show'])
    ->name('media.thumb')
    ->middleware(['throttle:60,1']); // Rate limit: 60 requests per minute
