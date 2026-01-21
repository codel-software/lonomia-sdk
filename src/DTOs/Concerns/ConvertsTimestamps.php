<?php

namespace CodelSoftware\LonomiaSdk\DTOs\Concerns;

use Carbon\Carbon;

/**
 * Trait para conversão de timestamps de string para formato numérico.
 * 
 * Converte strings de data no formato "Y-m-d H:i:s.u" para timestamps Unix
 * numéricos com microsegundos preservados.
 */
trait ConvertsTimestamps
{
    /**
     * Converte uma data string ou timestamp para timestamp numérico com microsegundos.
     * 
     * Aceita:
     * - String no formato "Y-m-d H:i:s" ou "Y-m-d H:i:s.u" → converte para float
     * - Timestamp numérico (int ou float) → retorna como float
     * - null → retorna null
     *
     * @param string|int|float|null $value Data em string ou timestamp numérico
     * @return float|null Timestamp numérico com microsegundos ou null
     */
    protected function convertToTimestamp(string|int|float|null $value): ?float
    {
        if ($value === null) {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            try {
                if (preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\.(\d+)$/', $value, $matches)) {
                    $dateTime = $matches[1];
                    $microseconds = $matches[2];

                    $carbon = Carbon::parse($dateTime);
                    $timestamp = (float) $carbon->timestamp;
                    $microsecondsFloat = (float) ('0.' . str_pad($microseconds, 6, '0', STR_PAD_RIGHT));

                    return $timestamp + $microsecondsFloat;
                }

                $carbon = Carbon::parse($value);
                return (float) $carbon->timestamp;
            } catch (\Exception $e) {
                $timestamp = strtotime($value);
                if ($timestamp !== false) {
                    return (float) $timestamp;
                }
            }
        }

        return null;
    }
}
