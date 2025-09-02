<?php

use Illuminate\Support\Facades\Route;
use MockServer\TestScenarios\Controllers\TestScenarioController;

// Test Scenario Control API endpoints
Route::prefix('test-scenarios')->group(function () {
    Route::post('/activate', [TestScenarioController::class, 'activate']);
    Route::get('/current', [TestScenarioController::class, 'current']);
    Route::post('/switch', [TestScenarioController::class, 'switch']);
    Route::post('/reset', [TestScenarioController::class, 'reset']);
    Route::get('/available', [TestScenarioController::class, 'available']);
    Route::get('/debug/{session_id}', [TestScenarioController::class, 'debug']);
    Route::get('/metrics', [TestScenarioController::class, 'metrics']);
});