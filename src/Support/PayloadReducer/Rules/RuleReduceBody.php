<?php

namespace CodelSoftware\LonomiaSdk\Support\PayloadReducer\Rules;

use CodelSoftware\LonomiaSdk\Support\PayloadReducer\ReductionRule;

/**
 * Regra de redução para request/response bodies.
 *
 * Prioridade 1 - Aplicada primeiro.
 * Reduz o tamanho dos bodies mantendo todas as chaves e estrutura.
 */
class RuleReduceBody extends ReductionRule
{
    public function getPriority(): int
    {
        return 1;
    }

    public function apply(array $payload, int $targetLimit): array
    {
        try {
            $maxStringLength = config('lonomia.reduction.body.max_string_length', 500);
            $maxArrayItems = config('lonomia.reduction.body.max_array_items', 50);
            $maxDepth = config('lonomia.reduction.body.max_depth', 5);

            // Reduz request.body
            if (isset($payload['request']['body']) && $payload['request']['body'] !== null) {
                $payload['request']['body'] = $this->truncator->truncateNestedStructure(
                    $payload['request']['body'],
                    $maxDepth,
                    $maxStringLength,
                    $maxArrayItems
                );
            }

            // Reduz response.body
            if (isset($payload['response']['body']) && $payload['response']['body'] !== null) {
                $payload['response']['body'] = $this->truncator->truncateNestedStructure(
                    $payload['response']['body'],
                    $maxDepth,
                    $maxStringLength,
                    $maxArrayItems
                );
            }

            return $payload;
        } catch (\Throwable $e) {
            // Em caso de erro, retorna payload original
            return $payload;
        }
    }
}
