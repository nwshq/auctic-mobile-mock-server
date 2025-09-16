<?php

namespace MockServer\MobileApi\Provider;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use MockServer\Services\MediaStorageService;
use MockServer\MobileApi\MediaUploadController;

class MobileApiProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MediaUploadController::class, function (Application $app) {
            return new MediaUploadController($app->make(MediaStorageService::class), config('app.url'), config('media.base_url'));
        });
    }
}