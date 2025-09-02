<?php

namespace MockServer\TestScenarios\Providers;

use Illuminate\Support\ServiceProvider;
use MockServer\TestScenarios\Services\TestSessionService;
use MockServer\TestScenarios\Services\ScenarioService;
use MockServer\TestScenarios\Services\ResponseGeneratorService;
use MockServer\TestScenarios\Middleware\TestScenarioMiddleware;

class TestScenarioServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register services as singletons
        $this->app->singleton(TestSessionService::class, function ($app) {
            return new TestSessionService();
        });

        $this->app->singleton(ScenarioService::class, function ($app) {
            return new ScenarioService();
        });

        $this->app->singleton(ResponseGeneratorService::class, function ($app) {
            return new ResponseGeneratorService();
        });

        // Register middleware
        $this->app->singleton(TestScenarioMiddleware::class, function ($app) {
            return new TestScenarioMiddleware(
                $app->make(TestSessionService::class),
                $app->make(ScenarioService::class),
                $app->make(ResponseGeneratorService::class)
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish config file
        $this->publishes([
            __DIR__ . '/../../../config/test-scenarios.php' => config_path('test-scenarios.php'),
        ], 'test-scenarios-config');

        // Publish scenario configurations
        $this->publishes([
            __DIR__ . '/../../../config/test-scenarios' => config_path('test-scenarios'),
        ], 'test-scenarios-files');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../../../database/migrations');
    }
}