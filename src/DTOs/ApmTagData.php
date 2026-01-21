<?php

namespace CodelSoftware\LonomiaSdk\DTOs;

/**
 * Data Transfer Object para dados de tag APM.
 * 
 * Representa uma tag APM com informações de início, fim e duração.
 */
class ApmTagData
{
    public function __construct(
        public float $start,
        public ?float $end = null,
        public ?float $duration = null,
    ) {}

    /**
     * Cria uma instância de ApmTagData a partir de um array.
     *
     * @param array $data Array com os dados da tag APM
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            start: (float) ($data['start'] ?? 0.0),
            end: isset($data['end']) ? (float) $data['end'] : null,
            duration: isset($data['duration']) ? (float) $data['duration'] : null,
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
            'start' => $this->start,
            'end' => $this->end,
            'duration' => $this->duration,
        ];
    }
}
