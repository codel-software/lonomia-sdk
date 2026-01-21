<?php

namespace CodelSoftware\LonomiaSdk\DTOs;

use CodelSoftware\LonomiaSdk\DTOs\Concerns\ConvertsTimestamps;

/**
 * Data Transfer Object para dados de operação de cache.
 * 
 * Representa informações sobre uma operação de cache (get, put, forget, etc.),
 * incluindo tipo de operação, chave, valor e métricas de performance.
 */
class CacheOperationData
{
    use ConvertsTimestamps;

    public function __construct(
        public string $operation,
        public ?string $key = null,
        public mixed $value = null,
        public ?int $valueSize = null,
        public ?float $executionTime = null,
        public ?float $executedAt = null,
        public bool $success = true,
        public ?string $errorMessage = null,
        public array $metadata = [],
    ) {}

    /**
     * Cria uma instância de CacheOperationData a partir de um array.
     * 
     * Converte automaticamente executed_at de string para timestamp numérico.
     *
     * @param array $data Array com os dados da operação de cache
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $instance = new self(
            operation: strtolower($data['operation'] ?? ''),
            key: $data['key'] ?? null,
            value: $data['value'] ?? null,
            valueSize: $data['value_size'] ?? null,
            executionTime: isset($data['execution_time']) ? (float) $data['execution_time'] : null,
            success: $data['success'] ?? true,
            errorMessage: $data['error_message'] ?? null,
            metadata: $data['metadata'] ?? [],
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
            'operation' => $this->operation,
            'key' => $this->key,
            'value' => $this->value,
            'value_size' => $this->valueSize,
            'execution_time' => $this->executionTime,
            'executed_at' => $this->executedAt,
            'success' => $this->success,
            'error_message' => $this->errorMessage,
            'metadata' => $this->metadata,
        ];
    }
}
