<?php

namespace CodelSoftware\LonomiaSdk\DTOs;

use CodelSoftware\LonomiaSdk\DTOs\Concerns\ConvertsTimestamps;

/**
 * Data Transfer Object para dados de requisição HTTP externa.
 * 
 * Representa informações sobre uma requisição HTTP feita para serviços externos,
 * incluindo URL, método, headers, corpo, resposta e métricas de performance.
 */
class ExternalRequestData
{
    use ConvertsTimestamps;

    public function __construct(
        public string $url,
        public string $method,
        public ?array $requestHeaders = null,
        public mixed $requestBody = null,
        public ?int $statusCode = null,
        public ?array $responseHeaders = null,
        public mixed $responseBody = null,
        public ?float $executionTime = null,
        public ?float $executedAt = null,
        public bool $success = true,
        public ?string $errorMessage = null,
    ) {}

    /**
     * Cria uma instância de ExternalRequestData a partir de um array.
     * 
     * Converte automaticamente executed_at de string para timestamp numérico.
     *
     * @param array $data Array com os dados da requisição externa
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $instance = new self(
            url: $data['url'] ?? '',
            method: strtoupper($data['method'] ?? 'GET'),
            requestHeaders: $data['request_headers'] ?? null,
            requestBody: $data['request_body'] ?? null,
            statusCode: $data['status_code'] ?? null,
            responseHeaders: $data['response_headers'] ?? null,
            responseBody: $data['response_body'] ?? null,
            executionTime: isset($data['execution_time']) ? (float) $data['execution_time'] : null,
            success: $data['success'] ?? true,
            errorMessage: $data['error_message'] ?? null,
        );

        if (isset($data['executed_at'])) {
            $instance->executedAt = $instance->convertToTimestamp($data['executed_at']);
        }

        return $instance;
    }

    /**
     * Converte o objeto para array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'method' => $this->method,
            'request_headers' => $this->requestHeaders,
            'request_body' => $this->requestBody,
            'status_code' => $this->statusCode,
            'response_headers' => $this->responseHeaders,
            'response_body' => $this->responseBody,
            'execution_time' => $this->executionTime,
            'executed_at' => $this->executedAt,
            'success' => $this->success,
            'error_message' => $this->errorMessage,
        ];
    }
}
