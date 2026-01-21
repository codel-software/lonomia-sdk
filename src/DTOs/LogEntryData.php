<?php

namespace CodelSoftware\LonomiaSdk\DTOs;

/**
 * Data Transfer Object para dados de entrada de log.
 * 
 * Representa uma entrada de log com timestamp, nível, mensagem e contexto.
 */
class LogEntryData
{
    public function __construct(
        public float $timestamp,
        public string $level,
        public string $message,
        public ?string $context = null,
    ) {}

    /**
     * Cria uma instância de LogEntryData a partir de um array.
     *
     * @param array $data Array com os dados da entrada de log
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            timestamp: (float) ($data['timestamp'] ?? microtime(true)),
            level: $data['level'] ?? 'info',
            message: $data['message'] ?? '',
            context: $data['context'] ?? null,
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
            'timestamp' => $this->timestamp,
            'level' => $this->level,
            'message' => $this->message,
            'context' => $this->context,
        ];
    }
}
