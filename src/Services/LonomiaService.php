<?php

namespace CodelSoftware\LonomiaSdk\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class LonomiaService
{
    protected $apiKey;
    protected $baseUrl;

    public function __construct(array $config)
    {

        $this->apiKey = $config['api_key'];
        $this->baseUrl = 'https://lonomia.codelsoftware.com.br';
        //$this->baseUrl = 'http://127.0.0.1';
    }

    public function captureError(\Throwable $exception, string $projectToken)
    {
        $payload = [
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'project_token' => $projectToken,
        ];

        return Http::withHeaders([
        ])->post("{$this->baseUrl}/api/error", $payload);
    }

    public function logRequest($request, $response, float $executionTime, string $projectToken)
    {
        // Dados a serem enviados
        $payload = [
            'route' => $request->path(),
            'method' => $request->method(),
            'status' => $response->status(),
            'user_id' => Auth::id() ?? 'guest', // ID do usuário ou 'guest'
            'execution_time' => $executionTime, // Tempo em segundos
            'project_token' => $projectToken,

        ];

        // Envia os dados para o endpoint
        return Http::withHeaders([
        ])->post("{$this->baseUrl}/api/requests", $payload);
    }
}