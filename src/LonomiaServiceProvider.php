<?php

namespace CodelSoftware\LonomiaSdk;

use Illuminate\Support\ServiceProvider;
use CodelSoftware\LonomiaSdk\Services\LonomiaService;

class LonomiaServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/Config/lonomia.php', 'lonomia');

        // Registra o serviço como singleton com um alias e com a classe
        $this->app->singleton(LonomiaService::class, function ($app) {
            return new LonomiaService(config('lonomia'));
        });

        // Alias opcional para facilitar o uso
        $this->app->alias(LonomiaService::class, 'lonomia-sdk');
    }

    public function boot()
    {
        $this->publishes([
            __DIR__ . '/Config/lonomia.php' => config_path('lonomia.php'),
        ], 'config');
    }
}
