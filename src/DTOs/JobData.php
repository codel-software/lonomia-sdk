<?php

namespace CodelSoftware\LonomiaSdk\DTOs;

use CodelSoftware\LonomiaSdk\DTOs\Concerns\ConvertsTimestamps;

/**
 * Data Transfer Object para dados de job.
 * 
 * Representa informações sobre um job (queued, processing, processed, failed),
 * incluindo status, classe, ID, conexão, fila e métricas de execução.
 */
class JobData
{
    use ConvertsTimestamps;

    public function __construct(
        public string $status,
        public string $jobClass,
        public ?string $jobId = null,
        public ?string $connection = null,
        public ?string $queue = null,
        public ?float $executionTime = null,
        public ?float $executedAt = null,
        public ?array $payload = null,
        public ?array $exception = null,
    ) {}

    /**
     * Cria uma instância de JobData a partir de um array.
     * 
     * Converte automaticamente executed_at de string para timestamp numérico.
     *
     * @param array $data Array com os dados do job
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $instance = new self(
            status: $data['status'] ?? '',
            jobClass: $data['job_class'] ?? '',
            jobId: $data['job_id'] ?? null,
            connection: $data['connection'] ?? null,
            queue: $data['queue'] ?? null,
            executionTime: isset($data['execution_time']) ? (float) $data['execution_time'] : null,
            payload: $data['payload'] ?? null,
        );

        if (isset($data['executed_at'])) {
            $instance->executedAt = $instance->convertToTimestamp($data['executed_at']);
        }

        if (isset($data['exception'])) {
            $instance->exception = is_array($data['exception']) 
                ? $data['exception'] 
                : [
                    'message' => $data['exception']['message'] ?? null,
                    'code' => $data['exception']['code'] ?? null,
                    'file' => $data['exception']['file'] ?? null,
                    'line' => $data['exception']['line'] ?? null,
                    'trace' => $data['exception']['trace'] ?? null,
                ];
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
            'status' => $this->status,
            'job_class' => $this->jobClass,
            'job_id' => $this->jobId,
            'connection' => $this->connection,
            'queue' => $this->queue,
            'execution_time' => $this->executionTime,
            'executed_at' => $this->executedAt,
            'payload' => $this->payload,
            'exception' => $this->exception,
        ];
    }
}
