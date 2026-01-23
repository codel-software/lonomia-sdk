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
     * Converte automaticamente arrays de context para JSON string.
     * Garante que o context seja sempre string ou null, nunca array.
     *
     * @param array $data Array com os dados da entrada de log
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $context = $data['context'] ?? null;
        
        // Converte arrays para JSON string
        if (is_array($context)) {
            $context = json_encode($context) ?: null;
        }
        
        // Garante que seja string ou null
        if ($context !== null && ! is_string($context)) {
            $context = (string) $context;
        }
        
        return new self(
            timestamp: (float) ($data['timestamp'] ?? microtime(true)),
            level: (string) ($data['level'] ?? 'info'),
            message: (string) ($data['message'] ?? ''),
            context: $context,
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
