<?php

namespace CodelSoftware\LonomiaSdk\Support;

use CodelSoftware\LonomiaSdk\Support\PayloadReducer\Rules\RuleFinalCut;
use CodelSoftware\LonomiaSdk\Support\PayloadReducer\Rules\RuleReduceBody;
use CodelSoftware\LonomiaSdk\Support\PayloadReducer\Rules\RuleReduceCache;
use CodelSoftware\LonomiaSdk\Support\PayloadReducer\Rules\RuleReduceLogs;
use CodelSoftware\LonomiaSdk\Support\PayloadReducer\Rules\RuleReduceRequests;
use CodelSoftware\LonomiaSdk\Support\PayloadReducer\ReductionRule;
use CodelSoftware\LonomiaSdk\Support\PayloadReducer\SizeChecker;

/**
 * Orquestrador principal para redução de payload.
 *
 * Aplica pipeline de regras progressivamente até o payload
 * estar dentro do limite configurado.
 */
class PayloadReducer
{
    protected SizeChecker $sizeChecker;

    /**
     * @var ReductionRule[]
     */
    protected array $rules = [];

    public function __construct()
    {
        $this->sizeChecker = new SizeChecker();

        // Registra todas as regras
        $this->rules = [
            new RuleReduceBody(),
            new RuleReduceLogs(),
            new RuleReduceRequests(),
            new RuleReduceCache(),
            new RuleFinalCut(),
        ];

        // Ordena por prioridade (menor = primeiro)
        usort($this->rules, function ($a, $b) {
            return $a->getPriority() <=> $b->getPriority();
        });
    }

    /**
     * Reduz o payload aplicando regras progressivamente.
     *
     * @param  array  $payload  Payload a ser reduzido
     * @param  bool  $isError  Se true, usa limite maior (erro); se false, usa limite menor (OK)
     * @return array Payload reduzido
     */
    public function reduce(array $payload, bool $isError = false): array
    {
        try {
            // Determina limite baseado no tipo de resposta
            $limit = $isError
                ? config('lonomia.max_payload_error', 512 * 1024) // 512KB (1/3 de 1.5MB)
                : config('lonomia.max_payload_ok', 100 * 1024); // 100KB (1/3 de 300KB)

            // Verifica se já está dentro do limite
            if (! $this->sizeChecker->exceedsLimit($payload, $limit)) {
                return $payload;
            }

            // Aplica regras progressivamente até estar dentro do limite
            $reduced = $payload;
            $maxIterations = 10; // Previne loop infinito
            $iteration = 0;

            while ($this->sizeChecker->exceedsLimit($reduced, $limit) && $iteration < $maxIterations) {
                $previousSize = $this->sizeChecker->getSize($reduced);

                // Aplica cada regra em sequência
                foreach ($this->rules as $rule) {
                    try {
                        $reduced = $rule->apply($reduced, $limit);
                    } catch (\Throwable $e) {
                        // Se uma regra falhar, continua com a próxima
                        continue;
                    }
                }

                $newSize = $this->sizeChecker->getSize($reduced);

                // Se não houve redução significativa, para para evitar loop
                if ($newSize >= $previousSize * 0.99) {
                    break;
                }

                $iteration++;
            }

            // Garante que sempre retorna um payload válido
            return $this->ensureValidPayload($reduced);
        } catch (\Throwable $e) {
            // Em caso de erro crítico, retorna payload mínimo válido
            return $this->getMinimalPayload($payload);
        }
    }

    /**
     * Garante que o payload é válido e tem estrutura mínima.
     */
    protected function ensureValidPayload(array $payload): array
    {
        // Garante que sempre tem tracking_id
        if (! isset($payload['tracking_id'])) {
            $payload['tracking_id'] = '';
        }

        // Garante que sempre tem request básico
        if (! isset($payload['request'])) {
            $payload['request'] = [
                'method' => 'GET',
                'url' => '',
            ];
        }

        return $payload;
    }

    /**
     * Retorna payload mínimo válido em caso de erro crítico.
     */
    protected function getMinimalPayload(array $originalPayload): array
    {
        return [
            'tracking_id' => $originalPayload['tracking_id'] ?? '',
            'request' => [
                'method' => $originalPayload['request']['method'] ?? 'GET',
                'url' => $originalPayload['request']['url'] ?? '',
            ],
            'response' => [
                'status' => $originalPayload['response']['status'] ?? 200,
            ],
            'exception' => $originalPayload['exception'] ?? null,
        ];
    }
}
