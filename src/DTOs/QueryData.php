<?php

namespace CodelSoftware\LonomiaSdk\DTOs;

use CodelSoftware\LonomiaSdk\DTOs\Concerns\ConvertsTimestamps;

/**
 * Data Transfer Object para dados de query SQL.
 * 
 * Representa informações sobre uma query SQL executada, incluindo
 * SQL, bindings, tempo de execução e timestamp de execução.
 */
class QueryData
{
    use ConvertsTimestamps;

    public function __construct(
        public string $sql,
        public array $bindings = [],
        public float $time = 0.0,
        public ?float $executedAt = null,
    ) {}

    /**
     * Cria uma instância de QueryData a partir de um array.
     * 
     * Converte automaticamente executed_at de string para timestamp numérico.
     *
     * @param array $data Array com os dados da query
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $instance = new self(
            sql: $data['sql'] ?? '',
            bindings: $data['bindings'] ?? [],
            time: (float) ($data['time'] ?? 0.0),
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
            'sql' => $this->sql,
            'bindings' => $this->bindings,
            'time' => $this->time,
            'executed_at' => $this->executedAt,
        ];
    }
}
