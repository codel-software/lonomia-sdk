<?php

namespace CodelSoftware\LonomiaSdk\DTOs;

/**
 * Data Transfer Object para dados de performance de requisições.
 * 
 * Representa métricas de performance coletadas durante a execução de uma requisição HTTP,
 * incluindo tempo de execução, uso de memória e informações sobre o tipo de resposta.
 * Facilita o debug e type safety ao substituir arrays genéricos por objetos tipados.
 *
 * @property float $executionTime Tempo de execução da requisição em segundos
 * @property int $memoryStart Memória inicial usada em bytes
 * @property int $memoryEnd Memória final usada em bytes
 * @property int $peakMemory Memória de pico usada em bytes
 * @property bool $isStreamed Indica se a resposta foi enviada como stream
 */
class PerformanceData
{
    private float $executionTime;
    private int $memoryStart;
    private int $memoryEnd;
    private int $peakMemory;
    private bool $isStreamed;

    /**
     * Cria uma instância de PerformanceData.
     * 
     * Aceita um array com as chaves 'execution_time', 'memory_start', 'memory_end',
     * 'peak_memory' e 'is_streamed', ou parâmetros individuais tipados.
     *
     * @param array|float $executionTime Array de dados ou valor float do tempo de execução
     * @param int|null $memoryStart Memória inicial em bytes (opcional se primeiro parâmetro for array)
     * @param int|null $memoryEnd Memória final em bytes (opcional se primeiro parâmetro for array)
     * @param int|null $peakMemory Memória de pico em bytes (opcional se primeiro parâmetro for array)
     * @param bool|null $isStreamed Se a resposta é streamed (opcional se primeiro parâmetro for array)
     */
    public function __construct(
        array|float $executionTime,
        ?int $memoryStart = null,
        ?int $memoryEnd = null,
        ?int $peakMemory = null,
        ?bool $isStreamed = null
    ) {
        // Se o primeiro parâmetro for array, usa ele para popular os dados
        if (is_array($executionTime)) {
            $data = $executionTime;
            $this->executionTime = $this->validateFloat($data['execution_time'] ?? 0.0, 'execution_time');
            $this->memoryStart = $this->validateInt($data['memory_start'] ?? 0, 'memory_start');
            $this->memoryEnd = $this->validateInt($data['memory_end'] ?? 0, 'memory_end');
            $this->peakMemory = $this->validateInt($data['peak_memory'] ?? 0, 'peak_memory');
            $this->isStreamed = $this->validateBool($data['is_streamed'] ?? false, 'is_streamed');
        } else {
            // Caso contrário, usa os parâmetros individuais
            $this->executionTime = $this->validateFloat($executionTime, 'execution_time');
            $this->memoryStart = $this->validateInt($memoryStart ?? 0, 'memory_start');
            $this->memoryEnd = $this->validateInt($memoryEnd ?? 0, 'memory_end');
            $this->peakMemory = $this->validateInt($peakMemory ?? 0, 'peak_memory');
            $this->isStreamed = $this->validateBool($isStreamed ?? false, 'is_streamed');
        }
    }

    /**
     * Valida e converte um valor para float.
     *
     * @param mixed $value Valor a ser validado
     * @param string $fieldName Nome do campo para mensagens de erro
     * @return float
     */
    private function validateFloat(mixed $value, string $fieldName): float
    {
        if (!is_numeric($value)) {
            throw new \InvalidArgumentException("Campo '{$fieldName}' deve ser numérico, recebido: " . gettype($value));
        }
        return (float) $value;
    }

    /**
     * Valida e converte um valor para int.
     *
     * @param mixed $value Valor a ser validado
     * @param string $fieldName Nome do campo para mensagens de erro
     * @return int
     */
    private function validateInt(mixed $value, string $fieldName): int
    {
        if (!is_numeric($value)) {
            throw new \InvalidArgumentException("Campo '{$fieldName}' deve ser numérico, recebido: " . gettype($value));
        }
        return (int) $value;
    }

    /**
     * Valida e converte um valor para bool.
     *
     * @param mixed $value Valor a ser validado
     * @param string $fieldName Nome do campo para mensagens de erro
     * @return bool
     */
    private function validateBool(mixed $value, string $fieldName): bool
    {
        return (bool) $value;
    }

    /**
     * Retorna o tempo de execução em segundos.
     *
     * @return float
     */
    public function getExecutionTime(): float
    {
        return $this->executionTime;
    }

    /**
     * Retorna a memória inicial em bytes.
     *
     * @return int
     */
    public function getMemoryStart(): int
    {
        return $this->memoryStart;
    }

    /**
     * Retorna a memória final em bytes.
     *
     * @return int
     */
    public function getMemoryEnd(): int
    {
        return $this->memoryEnd;
    }

    /**
     * Retorna a memória de pico em bytes.
     *
     * @return int
     */
    public function getPeakMemory(): int
    {
        return $this->peakMemory;
    }

    /**
     * Retorna se a resposta foi enviada como stream.
     *
     * @return bool
     */
    public function isStreamed(): bool
    {
        return $this->isStreamed;
    }

    /**
     * Converte o objeto para array com as chaves no formato snake_case.
     * 
     * Útil para serialização JSON ou compatibilidade com código legado que espera arrays.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'execution_time' => $this->executionTime,
            'memory_start' => $this->memoryStart,
            'memory_end' => $this->memoryEnd,
            'peak_memory' => $this->peakMemory,
            'is_streamed' => $this->isStreamed,
        ];
    }

    /**
     * Cria uma instância de PerformanceData a partir de um array.
     * 
     * Método estático alternativo para criar instâncias a partir de arrays.
     *
     * @param array $data Array com os dados de performance
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }
}
