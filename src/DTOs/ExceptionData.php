<?php

namespace CodelSoftware\LonomiaSdk\DTOs;

/**
 * Data Transfer Object para dados de exceção.
 * 
 * Representa informações sobre uma exceção, incluindo mensagem,
 * código, arquivo, linha, trace e snippet do arquivo.
 */
class ExceptionData
{
    public function __construct(
        public string $message,
        public int $code = 0,
        public ?string $file = null,
        public ?int $line = null,
        public ?array $trace = null,
        public ?array $fileSnippet = null,
    ) {}

    /**
     * Cria uma instância de ExceptionData a partir de um array.
     *
     * @param array $data Array com os dados da exceção
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            message: $data['message'] ?? '',
            code: $data['code'] ?? 0,
            file: $data['file'] ?? null,
            line: $data['line'] ?? null,
            trace: $data['trace'] ?? null,
            fileSnippet: $data['file_snippet'] ?? null,
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
            'message' => $this->message,
            'code' => $this->code,
            'file' => $this->file,
            'line' => $this->line,
            'trace' => $this->trace,
            'file_snippet' => $this->fileSnippet,
        ];
    }
}
