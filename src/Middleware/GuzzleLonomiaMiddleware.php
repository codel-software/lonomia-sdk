<?php

namespace CodelSoftware\LonomiaSdk\Middleware;

use GuzzleHttp\Middleware;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use CodelSoftware\LonomiaSdk\Facades\Lonomia;
use Illuminate\Support\Facades\Log;

class GuzzleLonomiaMiddleware
{
    /**
     * Cria middleware do Guzzle para capturar automaticamente chamadas HTTP.
     *
     * Este middleware intercepta requisições e respostas do Guzzle HTTP Client,
     * medindo o tempo de execução e registrando os dados no Lonomia SDK para
     * monitoramento de integrações externas.
     *
     * @return callable
     */
    public static function create(): callable
    {
        return Middleware::tap(
            function (RequestInterface $request, array &$options) {
                // Início da requisição
                $options['lonomia_start'] = microtime(true);
                $options['lonomia_request'] = [
                    'url' => (string) $request->getUri(),
                    'method' => $request->getMethod(),
                    'headers' => $request->getHeaders(),
                ];

                // Tenta extrair o body do request
                $body = $request->getBody();
                if ($body->getSize() > 0) {
                    $bodyContent = (string) $body;
                    // Tenta decodificar JSON
                    $decoded = json_decode($bodyContent, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $options['lonomia_request']['body'] = $decoded;
                    } else {
                        $options['lonomia_request']['body'] = $bodyContent;
                    }
                    // Reposiciona o body para que o Guzzle possa lê-lo novamente
                    $body->rewind();
                } else {
                    $options['lonomia_request']['body'] = null;
                }
            },
            function (RequestInterface $request, array $options, PromiseInterface $response) {
                // Fim da requisição
                $startTime = $options['lonomia_start'] ?? microtime(true);
                $executionTime = microtime(true) - $startTime;

                $response->then(
                    function (ResponseInterface $response) use ($request, $options, $executionTime) {
                        $statusCode = $response->getStatusCode();
                        $responseHeaders = $response->getHeaders();

                        // Obtém o body da resposta
                        $responseBody = (string) $response->getBody();
                        $responseBodyDecoded = null;
                        
                        // Tenta decodificar JSON se o content-type for JSON
                        $contentType = $response->getHeaderLine('Content-Type');
                        if (str_contains($contentType, 'application/json')) {
                            $responseBodyDecoded = json_decode($responseBody, true);
                            if (json_last_error() !== JSON_ERROR_NONE) {
                                $responseBodyDecoded = null;
                            }
                        }

                        Lonomia::addExternalRequest(
                            url: (string) $request->getUri(),
                            method: $request->getMethod(),
                            requestHeaders: $options['lonomia_request']['headers'] ?? [],
                            requestBody: $options['lonomia_request']['body'] ?? null,
                            statusCode: $statusCode,
                            responseHeaders: $responseHeaders,
                            responseBody: $responseBodyDecoded ?? $responseBody,
                            executionTime: $executionTime,
                            success: $statusCode >= 200 && $statusCode < 400
                        );
                    },
                    function (\Throwable $exception) use ($request, $options, $executionTime) {
                        // Trata erros/rejeições da promise
                        Lonomia::addExternalRequest(
                            url: (string) $request->getUri(),
                            method: $request->getMethod(),
                            requestHeaders: $options['lonomia_request']['headers'] ?? [],
                            requestBody: $options['lonomia_request']['body'] ?? null,
                            executionTime: $executionTime,
                            success: false,
                            errorMessage: $exception->getMessage()
                        );
                    }
                );
            }
        );
    }
}

