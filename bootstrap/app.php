<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        api: __DIR__.'/../routes/api.php',
        then: function ($router) {
            Route::middleware('api')
                ->group(base_path('routes/mobile-api.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Add Test Scenario Middleware to API groups
        $middleware->group('api', [
            \MockServer\TestScenarios\Middleware\TestScenarioMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
