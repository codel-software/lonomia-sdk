<?php

namespace CodelSoftware\LonomiaSdk\Support\PayloadReducer\Rules;

use CodelSoftware\LonomiaSdk\Support\PayloadReducer\ReductionRule;

/**
 * Regra de redução para logs.
 *
 * Prioridade 2.
 * Limita quantidade de logs e trunca mensagens longas.
 * Preserva: nível, timestamp, mensagem principal, stack trace (em erro).
 */
class RuleReduceLogs extends ReductionRule
{
    public function getPriority(): int
    {
        return 2;
    }

    public function apply(array $payload, int $targetLimit): array
    {
        try {
            if (! isset($payload['logs']) || ! is_array($payload['logs'])) {
                return $payload;
            }

            $maxCount = config('lonomia.reduction.logs.max_count', 100);
            $maxMessageLength = config('lonomia.reduction.logs.max_message_length', 1000);

            // Limita quantidade de logs
            $logs = $payload['logs'];
            if (count($logs) > $maxCount) {
                // Mantém os últimos N logs (mais recentes)
                $logs = array_slice($logs, -$maxCount);
            }

            // Trunca mensagens e contextos longos
            $reducedLogs = [];
            foreach ($logs as $log) {
                $reducedLog = $log;

                // Trunca mensagem
                if (isset($log['message']) && is_string($log['message'])) {
                    $reducedLog['message'] = $this->truncator->truncateString(
                        $log['message'],
                        $maxMessageLength
                    );
                }

                // Trunca contexto se for string
                if (isset($log['context']) && is_string($log['context'])) {
                    $reducedLog['context'] = $this->truncator->truncateString(
                        $log['context'],
                        $maxMessageLength
                    );
                } elseif (isset($log['context']) && is_array($log['context'])) {
                    // Se contexto for array, reduz recursivamente
                    $reducedLog['context'] = $this->truncator->truncateNestedStructure(
                        $log['context'],
                        3, // Profundidade menor para contexto
                        500, // Strings menores
                        20 // Menos itens
                    );
                }

                // Preserva campos essenciais: level, timestamp, message, trace
                $reducedLogs[] = $reducedLog;
            }

            $payload['logs'] = $reducedLogs;

            return $payload;
        } catch (\Throwable $e) {
            // Em caso de erro, retorna payload original
            return $payload;
        }
    }
}
