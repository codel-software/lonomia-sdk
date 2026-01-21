<?php

namespace CodelSoftware\LonomiaSdk\Support\PayloadReducer\Rules;

use CodelSoftware\LonomiaSdk\Support\PayloadReducer\ReductionRule;

/**
 * Regra de redução para operações de cache.
 *
 * Prioridade 4.
 * Limita quantidade de operações e trunca valores grandes.
 */
class RuleReduceCache extends ReductionRule
{
    public function getPriority(): int
    {
        return 4;
    }

    public function apply(array $payload, int $targetLimit): array
    {
        try {
            if (! isset($payload['cache']) || ! is_array($payload['cache'])) {
                return $payload;
            }

            $maxOperations = config('lonomia.reduction.cache.max_operations', 100);

            $cache = $payload['cache'];

            // Limita quantidade de operações
            if (count($cache) > $maxOperations) {
                // Mantém as últimas N operações (mais recentes)
                $cache = array_slice($cache, -$maxOperations);
            }

            // Trunca valores grandes
            $reducedCache = [];
            foreach ($cache as $operation) {
                $reducedOp = $operation;

                // Trunca value se for muito grande
                if (isset($operation['value'])) {
                    if (is_string($operation['value'])) {
                        if (strlen($operation['value']) > 500) {
                            $reducedOp['value'] = $this->truncator->truncateString(
                                $operation['value'],
                                500
                            );
                        }
                    } elseif (is_array($operation['value'])) {
                        $reducedOp['value'] = $this->truncator->truncateNestedStructure(
                            $operation['value'],
                            3,
                            500,
                            20
                        );
                    }
                }

                // Preserva campos essenciais
                $reducedCache[] = $reducedOp;
            }

            $payload['cache'] = $reducedCache;

            return $payload;
        } catch (\Throwable $e) {
            // Em caso de erro, retorna payload original
            return $payload;
        }
    }
}
