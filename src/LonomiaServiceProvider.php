<?php

namespace CodelSoftware\LonomiaSdk;

use Illuminate\Support\ServiceProvider;

class LonomiaServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/Config/lonomia.php', 'lonomia');

        $this->app->singleton('lonomia-sdk', function ($app) {
            return new Services\LonomiaService(config('lonomia'));
        });
    }

    public function boot()
    {
        $this->publishes([
            __DIR__ . '/Config/lonomia.php' => config_path('lonomia.php'),
        ], 'config');
    }
}