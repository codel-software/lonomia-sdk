<?php

namespace CodelSoftware\LonomiaSdk\DTOs;

/**
 * Data Transfer Object para dados de requisição HTTP.
 * 
 * Representa informações sobre a requisição HTTP recebida, incluindo
 * método, URL, headers e corpo da requisição.
 */
class RequestData
{
    public function __construct(
        public string $method,
        public string $url,
        public array $headers = [],
        public mixed $body = null,
    ) {}

    /**
     * Cria uma instância de RequestData a partir de um array.
     *
     * @param array $data Array com os dados da requisição
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            method: $data['method'] ?? 'GET',
            url: $data['url'] ?? '',
            headers: $data['headers'] ?? [],
            body: $data['body'] ?? null,
        );
    }

    /**
     * Converte o objeto para array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'method' => $this->method,
            'url' => $this->url,
            'headers' => $this->headers,
            'body' => $this->body,
        ];
    }
}
