<?php

namespace CodelSoftware\LonomiaSdk;

use Illuminate\Support\ServiceProvider;
use CodelSoftware\LonomiaSdk\Services\LonomiaService;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use CodelSoftware\LonomiaSdk\Listeners\HttpClientListener;
use CodelSoftware\LonomiaSdk\Listeners\LogListener;
use CodelSoftware\LonomiaSdk\Listeners\CacheListener;
use CodelSoftware\LonomiaSdk\Listeners\JobListener;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Cache\Events\KeyForgotten;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;

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

        // Registra listeners para eventos HTTP do Laravel
        if (env('LONOMIA_ENABLED', true) == true) {
            $httpListener = new HttpClientListener();
            
            Event::listen(RequestSending::class, [$httpListener, 'handleRequestSending']);
            Event::listen(ResponseReceived::class, [$httpListener, 'handleResponseReceived']);
            
            // Registra listener para capturar logs do Laravel
            $logListener = new LogListener();
            Event::listen(MessageLogged::class, [$logListener, 'handle']);
            
            // Registra listener para capturar operações de cache/Redis
            $cacheListener = new CacheListener();
            Event::listen(CacheHit::class, [$cacheListener, 'handleCacheHit']);
            Event::listen(CacheMissed::class, [$cacheListener, 'handleCacheMissed']);
            Event::listen(KeyWritten::class, [$cacheListener, 'handleKeyWritten']);
            Event::listen(KeyForgotten::class, [$cacheListener, 'handleKeyForgotten']);
            
            // Registra listener para capturar eventos de jobs
            $jobListener = new JobListener();
            Event::listen(JobQueued::class, [$jobListener, 'handleJobQueued']);
            Event::listen(JobProcessing::class, [$jobListener, 'handleJobProcessing']);
            Event::listen(JobProcessed::class, [$jobListener, 'handleJobProcessed']);
            Event::listen(JobFailed::class, [$jobListener, 'handleJobFailed']);
        }
    }
}
