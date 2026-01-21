<?php

namespace CodelSoftware\LonomiaSdk\Support\PayloadReducer\Rules;

use CodelSoftware\LonomiaSdk\Support\PayloadReducer\ReductionRule;

/**
 * Regra final de corte forçado.
 *
 * Prioridade 5 - Última regra aplicada.
 * Remove seções inteiras menos prioritárias se ainda exceder limite.
 * Mantém apenas: metadata, request básico, response básico, erro/stack trace.
 */
class RuleFinalCut extends ReductionRule
{
    public function getPriority(): int
    {
        return 5;
    }

    public function apply(array $payload, int $targetLimit): array
    {
        try {
            // Mantém sempre: tracking_id, request básico, response básico, exception
            $minimal = [
                'tracking_id' => $payload['tracking_id'] ?? '',
            ];

            // Request básico (sem body completo)
            if (isset($payload['request'])) {
                $minimal['request'] = [
                    'method' => $payload['request']['method'] ?? 'GET',
                    'url' => $payload['request']['url'] ?? '',
                    'headers' => $this->reduceHeadersMinimal($payload['request']['headers'] ?? []),
                    // Body removido ou muito reduzido
                ];
            }

            // Response básico (sem body completo)
            if (isset($payload['response'])) {
                $minimal['response'] = [
                    'status' => $payload['response']['status'] ?? 200,
                    'headers' => $this->reduceHeadersMinimal($payload['response']['headers'] ?? []),
                    // Body removido
                ];
            }

            // Performance básico
            if (isset($payload['performance'])) {
                $minimal['performance'] = [
                    'execution_time' => $payload['performance']['execution_time'] ?? null,
                    'peak_memory' => $payload['performance']['peak_memory'] ?? null,
                ];
            }

            // Exception completa (máxima prioridade em erros)
            if (isset($payload['exception']) && $payload['exception'] !== null) {
                $minimal['exception'] = $payload['exception'];
            }

            // Queries - mantém apenas as mais lentas (últimas 10)
            if (isset($payload['queries']) && is_array($payload['queries'])) {
                $queries = $payload['queries'];
                // Ordena por tempo (mais lento primeiro) e mantém top 10
                usort($queries, function ($a, $b) {
                    $timeA = $a['time'] ?? 0;
                    $timeB = $b['time'] ?? 0;
                    return $timeB <=> $timeA;
                });
                $minimal['queries'] = array_slice($queries, 0, 10);
            }

            // Logs - mantém apenas erros e warnings (últimos 20)
            if (isset($payload['logs']) && is_array($payload['logs'])) {
                $logs = $payload['logs'];
                // Filtra apenas erros, warnings e críticos
                $importantLogs = array_filter($logs, function ($log) {
                    $level = strtolower($log['level'] ?? '');
                    return in_array($level, ['error', 'warning', 'critical', 'alert', 'emergency']);
                });
                // Mantém últimos 20
                $minimal['logs'] = array_slice(array_values($importantLogs), -20);
            }

            // Remove seções menos prioritárias
            // http_requests, external_requests, cache, jobs, apm - todos removidos

            return $minimal;
        } catch (\Throwable $e) {
            // Em caso de erro, retorna payload mínimo seguro
            return [
                'tracking_id' => $payload['tracking_id'] ?? '',
                'request' => [
                    'method' => $payload['request']['method'] ?? 'GET',
                    'url' => $payload['request']['url'] ?? '',
                ],
                'response' => [
                    'status' => $payload['response']['status'] ?? 200,
                ],
                'exception' => $payload['exception'] ?? null,
            ];
        }
    }

    /**
     * Reduz headers ao mínimo essencial.
     */
    private function reduceHeadersMinimal(array $headers): array
    {
        $essential = ['content-type', 'content-length'];
        $reduced = [];

        foreach ($headers as $key => $value) {
            if (in_array(strtolower($key), $essential)) {
                $reduced[$key] = $value;
            }
        }

        return $reduced;
    }
}
