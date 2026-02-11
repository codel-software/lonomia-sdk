<?php

namespace CodelSoftware\LonomiaSdk\Support\PayloadReducer\Rules;

use CodelSoftware\LonomiaSdk\Support\PayloadReducer\ReductionRule;

/**
 * Regra de redução para jobs (filas).
 *
 * Prioridade 6. Limita quantidade de jobs e trunca payload/data
 * de cada job para reduzir tamanho.
 */
class RuleReduceJobs extends ReductionRule
{
    public function getPriority(): int
    {
        return 6;
    }

    public function apply(array $payload, int $targetLimit): array
    {
        try {
            if (! isset($payload['jobs']) || ! is_array($payload['jobs'])) {
                return $payload;
            }

            $maxCount = config('lonomia.reduction.jobs.max_count', 5);

            $jobs = $payload['jobs'];
            if (count($jobs) > $maxCount) {
                $jobs = array_slice($jobs, -$maxCount);
            }

            $reduced = [];
            foreach ($jobs as $job) {
                $reducedJob = $job;

                if (isset($job['payload']) && $job['payload'] !== null) {
                    $reducedJob['payload'] = $this->truncator->truncateNestedStructure(
                        $job['payload'],
                        3,
                        200,
                        10
                    );
                }

                $reduced[] = $reducedJob;
            }

            $payload['jobs'] = $reduced;

            return $payload;
        } catch (\Throwable $e) {
            return $payload;
        }
    }
}
