<?php

namespace CodelSoftware\LonomiaSdk\Listeners;

use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use CodelSoftware\LonomiaSdk\Services\LonomiaService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

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

        // Debug apenas em desenvolvimento
        if (env('APP_DEBUG', false)) {
            Log::debug('Lonomia: Requisição HTTP iniciada', [
                'request_id' => $requestId,
            ]);
        }
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
        $executionTime = $endTime - $startTime;

        // Limpa o tempo de início para liberar memória
        unset(self::$requestStartTimes[$requestId]);

        // Debug: verifica se o evento está sendo capturado
        if (env('APP_DEBUG', false)) {
            Log::debug('Lonomia: Evento ResponseReceived capturado', [
                'request_id' => $requestId,
                'response_class' => get_class($event->response),
                'request_class' => get_class($event->request),
            ]);
        }

        try {
            $lonomia = App::make(LonomiaService::class);
            
            // O Laravel HTTP Client usa PendingRequest e Response
            // A URL está disponível via toPsrRequest() ou diretamente no response
            $url = null;
            $method = 'GET';
            $requestHeaders = [];
            $requestBody = null;
            
            // Método 1: Tenta obter a URL do response (mais confiável no Laravel)
            try {
                if (method_exists($event->response, 'effectiveUri')) {
                    $url = (string) $event->response->effectiveUri();
                }
            } catch (\Throwable $e) {
                // Ignora
            }
            
            // Método 2: Tenta obter do request usando toPsrRequest() (método público)
            if (!$url && method_exists($event->request, 'toPsrRequest')) {
                try {
                    $psrRequest = $event->request->toPsrRequest();
                    $url = (string) $psrRequest->getUri();
                    $method = strtoupper($psrRequest->getMethod());
                    $requestHeaders = $psrRequest->getHeaders();
                    $requestBody = (string) $psrRequest->getBody();
                    
                    // Tenta decodificar JSON se for o caso
                    if ($requestBody) {
                        $decoded = json_decode($requestBody, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $requestBody = $decoded;
                        }
                    }
                } catch (\Throwable $e) {
                    // Ignora erros
                }
            }
            
            // Método 3: Tenta obter do request usando reflection (fallback)
            if (!$url) {
                try {
                    $reflection = new \ReflectionClass($event->request);
                    
                    // Tenta obter URL
                    if ($reflection->hasProperty('url')) {
                        $property = $reflection->getProperty('url');
                        $property->setAccessible(true);
                        $urlValue = $property->getValue($event->request);
                        if ($urlValue) {
                            $url = (string) $urlValue;
                        }
                    }
                    
                    // Obtém o método
                    if ($reflection->hasProperty('method')) {
                        $property = $reflection->getProperty('method');
                        $property->setAccessible(true);
                        $methodValue = $property->getValue($event->request);
                        if ($methodValue) {
                            $method = strtoupper($methodValue);
                        }
                    }
                    
                    // Obtém headers
                    if ($reflection->hasProperty('headers')) {
                        $property = $reflection->getProperty('headers');
                        $property->setAccessible(true);
                        $headers = $property->getValue($event->request);
                        if (is_array($headers)) {
                            $requestHeaders = $headers;
                        }
                    }
                    
                    // Obtém body/data
                    if ($reflection->hasProperty('data')) {
                        $property = $reflection->getProperty('data');
                        $property->setAccessible(true);
                        $requestBody = $property->getValue($event->request);
                    } elseif ($reflection->hasProperty('pendingBody')) {
                        $property = $reflection->getProperty('pendingBody');
                        $property->setAccessible(true);
                        $requestBody = $property->getValue($event->request);
                    }
                } catch (\ReflectionException $e) {
                    // Se reflection falhar, continua com valores padrão
                }
            }
            
            // Obtém informações da resposta
            $statusCode = $event->response->status();
            $responseHeaders = $event->response->headers();
            $responseBody = $event->response->body();
            
            // Tenta decodificar JSON do response
            $responseBodyDecoded = null;
            $contentType = $responseHeaders['Content-Type'][0] ?? $responseHeaders['content-type'][0] ?? '';
            if (str_contains($contentType, 'application/json')) {
                $responseBodyDecoded = json_decode($responseBody, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $responseBodyDecoded = null;
                }
            }
            
            // Se ainda não tem URL, usa fallback
            if (!$url) {
                $url = 'unknown';
            }

            // Converte headers do Laravel para array simples
            $requestHeadersArray = [];
            foreach ($requestHeaders as $key => $value) {
                if (is_array($value)) {
                    $requestHeadersArray[$key] = implode(', ', $value);
                } else {
                    $requestHeadersArray[$key] = $value;
                }
            }
            
            $responseHeadersArray = [];
            foreach ($responseHeaders as $key => $value) {
                if (is_array($value)) {
                    $responseHeadersArray[$key] = implode(', ', $value);
                } else {
                    $responseHeadersArray[$key] = $value;
                }
            }

            // Usa addExternalRequest que é mais adequado para requisições HTTP externas
            $lonomia->addExternalRequest(
                url: $url,
                method: $method,
                requestHeaders: $requestHeadersArray,
                requestBody: $requestBody,
                statusCode: $statusCode,
                responseHeaders: $responseHeadersArray,
                responseBody: $responseBodyDecoded ?? $responseBody,
                executionTime: $executionTime,
                success: $statusCode >= 200 && $statusCode < 400
            );
            
            // Debug apenas em desenvolvimento
            if (env('APP_DEBUG', false)) {
                Log::debug('Lonomia: Requisição HTTP capturada', [
                    'url' => $url,
                    'method' => $method,
                    'status_code' => $statusCode,
                    'execution_time' => $executionTime,
                ]);
            }
        } catch (\Throwable $e) {
            // Silenciosamente ignora erros para não afetar o fluxo da aplicação
            // Log apenas em desenvolvimento
            if (env('APP_DEBUG', false)) {
                Log::debug('Erro ao capturar requisição HTTP no Lonomia: ' . $e->getMessage(), [
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }
    }
}

