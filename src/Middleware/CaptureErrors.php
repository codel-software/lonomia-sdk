<?php

namespace CodelSoftware\LonomiaSdk\Middleware;

use Closure;
use CodelSoftware\LonomiaSdk\Facades\Lonomia;

class CaptureErrors
{
    public function handle($request, Closure $next)
    {
        $startTime = microtime(true); // Marca o início da requisição

        $response = $next($request); // Processa a requisição

        $endTime = microtime(true); // Marca o fim da requisição
        $executionTime = $endTime - $startTime; // Calcula o tempo de execução

        

        if($response->exception){
            Lonomia::captureError($response->exception, config('lonomia.api_key'));
        }

        // Chama o método do SDK para logar a requisição
        Lonomia::logRequest($request, $response, $executionTime, config('lonomia.api_key'));

        return $response;
    }
}