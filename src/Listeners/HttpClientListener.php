<?php

namespace CodelSoftware\LonomiaSdk\Listeners;

use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use CodelSoftware\LonomiaSdk\Services\LonomiaService;
use Illuminate\Support\Facades\App;

class HttpClientListener
{
    protected static $requestStartTimes = [];

    /**
     * Handle o evento de requisição HTTP sendo enviada.
     */
    public function handleRequestSending(RequestSending $event): void
    {
        if (env('LONOMIA_ENABLED', true) == false) {
            return;
        }

        $requestId = spl_object_hash($event->request);
        self::$requestStartTimes[$requestId] = microtime(true);

        // Podemos logar o início da requisição aqui se necessário
    }

    /**
     * Handle o evento de resposta HTTP recebida.
     */
    public function handleResponseReceived(ResponseReceived $event): void
    {
        if (env('LONOMIA_ENABLED', true) == false) {
            return;
        }

        $requestId = spl_object_hash($event->request);
        $startTime = self::$requestStartTimes[$requestId] ?? microtime(true);
        $endTime = microtime(true);

        // Limpa o tempo de início para liberar memória
        unset(self::$requestStartTimes[$requestId]);

        try {
            $lonomia = App::make(LonomiaService::class);
            
            $request = $event->request;
            $options = [];
            
            // Obtém a URL - tenta diferentes métodos
            $url = null;
            if (method_exists($request, 'getUrl')) {
                $url = $request->getUrl();
            } elseif (method_exists($request, 'url')) {
                $url = $request->url();
            } elseif (property_exists($request, 'url')) {
                $url = $request->url;
            }
            
            // Obtém o método HTTP
            $method = 'GET';
            if (method_exists($request, 'getMethod')) {
                $method = strtoupper($request->getMethod());
            } elseif (property_exists($request, 'method')) {
                $method = strtoupper($request->method);
            }
            
            // Extrai opções do request (headers, body, etc)
            if (method_exists($request, 'getOptions')) {
                $requestOptions = $request->getOptions();
                $options = $requestOptions;
            } elseif (method_exists($request, 'options')) {
                $options = $request->options();
            }
            
            // Tenta extrair headers se não estiverem nas opções
            if (!isset($options['headers']) && method_exists($request, 'getHeaders')) {
                $options['headers'] = $request->getHeaders();
            } elseif (!isset($options['headers']) && method_exists($request, 'headers')) {
                $options['headers'] = $request->headers();
            }
            
            // Obtém informações da resposta
            $statusCode = $event->response->status();
            $responseHeaders = $event->response->headers();
            $responseBody = $event->response->body();
            
            // Se não conseguiu obter URL, tenta do response
            if (!$url) {
                $url = $event->response->effectiveUri() ?? 'unknown';
            }

            $lonomia->addHttpRequest(
                $method,
                (string) $url,
                $options,
                $startTime,
                $endTime,
                $statusCode,
                $responseHeaders,
                $responseBody
            );
        } catch (\Throwable $e) {
            // Silenciosamente ignora erros para não afetar o fluxo da aplicação
            // Log apenas em desenvolvimento
            if (env('APP_DEBUG', false)) {
                \Log::debug('Erro ao capturar requisição HTTP no Lonomia: ' . $e->getMessage());
            }
        }
    }
}

