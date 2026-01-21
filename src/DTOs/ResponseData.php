<?php

namespace CodelSoftware\LonomiaSdk\DTOs;

/**
 * Data Transfer Object para dados de resposta HTTP.
 * 
 * Representa informações sobre a resposta HTTP enviada, incluindo
 * status code, headers e corpo da resposta.
 */
class ResponseData
{
    public function __construct(
        public int $status,
        public array $headers = [],
        public mixed $body = null,
    ) {}

    /**
     * Cria uma instância de ResponseData a partir de um array.
     *
     * @param array $data Array com os dados da resposta
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            status: $data['status'] ?? 200,
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
            'status' => $this->status,
            'headers' => $this->headers,
            'body' => $this->body,
        ];
    }
}
