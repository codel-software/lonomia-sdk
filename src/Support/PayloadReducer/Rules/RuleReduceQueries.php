<?php

namespace CodelSoftware\LonomiaSdk\Support\PayloadReducer\Rules;

use CodelSoftware\LonomiaSdk\Support\PayloadReducer\ReductionRule;

/**
 * Regra de redução para queries SQL.
 *
 * Prioridade 2. Limita quantidade de queries, trunca sql e bindings
 * para reduzir tamanho mantendo estrutura e métricas (time).
 */
class RuleReduceQueries extends ReductionRule
{
    public function getPriority(): int
    {
        return 2;
    }

    public function apply(array $payload, int $targetLimit): array
    {
        try {
            if (! isset($payload['queries']) || ! is_array($payload['queries'])) {
                return $payload;
            }

            $maxCount = config('lonomia.reduction.queries.max_count', 20);
            $maxSqlLength = config('lonomia.reduction.queries.max_sql_length', 250);
            $maxBindingsCount = config('lonomia.reduction.queries.max_bindings_count', 5);
            $maxBindingLength = config('lonomia.reduction.queries.max_binding_length', 80);

            $queries = $payload['queries'];

            if (count($queries) > $maxCount) {
                $queries = array_slice($queries, -$maxCount);
            }

            $reduced = [];
            foreach ($queries as $q) {
                $reduced[] = $this->reduceQuery($q, $maxSqlLength, $maxBindingsCount, $maxBindingLength);
            }

            $payload['queries'] = $reduced;

            return $payload;
        } catch (\Throwable $e) {
            return $payload;
        }
    }

    /**
     * Reduz um único item de query: trunca sql e bindings.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function reduceQuery(array $query, int $maxSqlLength, int $maxBindingsCount, int $maxBindingLength): array
    {
        $out = $query;

        if (isset($query['sql']) && is_string($query['sql'])) {
            $out['sql'] = $this->truncator->truncateString($query['sql'], $maxSqlLength);
        }

        if (isset($query['bindings']) && is_array($query['bindings'])) {
            $bindings = $query['bindings'];
            if (count($bindings) > $maxBindingsCount) {
                $bindings = array_slice($bindings, 0, $maxBindingsCount);
            }
            $out['bindings'] = array_map(function ($v) use ($maxBindingLength) {
                if (is_string($v) && strlen($v) > $maxBindingLength) {
                    return $this->truncator->truncateString($v, $maxBindingLength);
                }

                return $v;
            }, array_values($bindings));
        }

        return $out;
    }
}
