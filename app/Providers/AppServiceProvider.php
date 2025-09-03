<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register MockDataService as singleton
        $this->app->singleton(\MockServer\Services\MockDataService::class, function () {
            return new \MockServer\Services\MockDataService();
        });
        
        // Register MediaStorageService as singleton
        $this->app->singleton(\MockServer\Services\MediaStorageService::class, function () {
            return new \MockServer\Services\MediaStorageService();
        });
        
        // Register Test Scenario Service Provider
        $this->app->register(\MockServer\TestScenarios\Providers\TestScenarioServiceProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
