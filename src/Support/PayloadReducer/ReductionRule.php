<?php

namespace CodelSoftware\LonomiaSdk\Support\PayloadReducer;

/**
 * Classe abstrata base para todas as regras de redução.
 *
 * Define a interface comum para aplicar regras de redução no payload.
 * Cada regra deve implementar o método apply() e definir sua prioridade.
 */
abstract class ReductionRule
{
    protected DataTruncator $truncator;

    public function __construct()
    {
        $this->truncator = new DataTruncator();
    }

    /**
     * Aplica a regra de redução no payload.
     *
     * @param  array  $payload  Payload a ser reduzido
     * @param  int  $targetLimit  Limite alvo em bytes
     * @return array Payload reduzido
     */
    abstract public function apply(array $payload, int $targetLimit): array;

    /**
     * Retorna a prioridade da regra (ordem de aplicação).
     *
     * Regras com prioridade menor são aplicadas primeiro.
     *
     * @return int Prioridade (1 = primeira, 5 = última)
     */
    abstract public function getPriority(): int;

    /**
     * Retorna o nome da regra para logs/debug.
     *
     * @return string Nome da regra
     */
    public function getName(): string
    {
        return class_basename(static::class);
    }
}
