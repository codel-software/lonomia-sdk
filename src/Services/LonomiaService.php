<?php

namespace CodelSoftware\LonomiaSdk\Services;

use Illuminate\Support\Facades\Http;

class LonomiaService
{
    protected $apiKey;
    protected $baseUrl;

    public function __construct(array $config)
    {
        $this->apiKey = $config['api_key'];
        //$this->baseUrl = 'https://api.lonomia.com';
        $this->baseUrl = 'http://127.0.0.1';
    }

    public function captureError(\Throwable $exception, string $projectToken)
    {
        dd(1);
        $payload = [
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'project_token' => $projectToken,
        ];

        return Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
        ])->post("{$this->baseUrl}/errors", $payload);
    }
}