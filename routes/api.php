<?php

use Illuminate\Support\Facades\Route;
use MockServer\TestScenarios\Controllers\TestScenarioController;
use MockServer\TestScenarios\Controllers\CameraPerformanceAnalysisController;
use MockServer\TestScenarios\Controllers\RotationTestAnalysisController;

// Test Scenario Control API endpoints
Route::prefix('test-scenarios')->group(function () {
    Route::post('/activate', [TestScenarioController::class, 'activate']);
    Route::get('/current', [TestScenarioController::class, 'current']);
    Route::post('/switch', [TestScenarioController::class, 'switch']);
    Route::post('/reset', [TestScenarioController::class, 'reset']);
    Route::get('/available', [TestScenarioController::class, 'available']);
    Route::get('/debug/{session_id}', [TestScenarioController::class, 'debug']);
    Route::get('/metrics', [TestScenarioController::class, 'metrics']);
    
    // Camera Performance Analysis endpoints
    Route::prefix('camera-performance')->group(function () {
        Route::get('/analysis', [CameraPerformanceAnalysisController::class, 'getAnalysis']);
        Route::post('/clear', [CameraPerformanceAnalysisController::class, 'clearTracking']);
    });

    // Rotation Test Analysis endpoints
    Route::prefix('rotation-test')->group(function () {
        Route::get('/analysis', [RotationTestAnalysisController::class, 'getAnalysis']);
        Route::post('/clear', [RotationTestAnalysisController::class, 'clearTracking']);
    });
});