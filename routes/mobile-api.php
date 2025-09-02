<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register Mobile API routes for your application.
| These routes are loaded by the RouteServiceProvider and all of them
| will be assigned to the "api" middleware group.
|
*/

// Mobile profile endpoint for JWT generation
Route::get('/mobile/profile', \MockServer\Auth\JWToken\MockJWTtokenController::class);

// Mobile API v1 endpoints
Route::prefix('mobile-api/v1')->group(function () {
    Route::get('/user', \MockServer\MobileApi\UserController::class);
    
    // Catalog sync endpoints
    Route::prefix('catalog')->group(function () {
        Route::get('/hydrate', [\MockServer\MobileApi\CatalogController::class, 'hydrate'])->name('catalog.hydrate');
        Route::get('/sync', [\MockServer\MobileApi\CatalogController::class, 'sync'])->name('catalog.sync');
    });
});