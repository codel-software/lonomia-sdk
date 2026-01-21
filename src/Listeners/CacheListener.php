<?php

namespace CodelSoftware\LonomiaSdk\Listeners;

use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Cache\Events\KeyForgotten;
use CodelSoftware\LonomiaSdk\Services\LonomiaService;
use Illuminate\Support\Facades\App;

class CacheListener
{
    /**
     * Armazena os tempos de início das operações para calcular duração.
     */
    private static array $operationStartTimes = [];

    /**
     * Handle o evento de cache hit (chave encontrada).
     */
    public function handleCacheHit(CacheHit $event): void
    {
        if (env('LONOMIA_ENABLED', true) == false) {
            return;
        }

        try {
            $lonomia = App::make(LonomiaService::class);
            
            // Calcula o tempo de execução se houver início registrado
            $executionTime = $this->calculateExecutionTime('get_' . $event->key);
            
            $lonomia->addCacheOperation(
                operation: 'get',
                key: $event->key,
                value: $this->serializeValue($event->value),
                executionTime: $executionTime,
                success: true,
                metadata: [
                    'store' => $event->store ?? null,
                    'hit' => true,
                ]
            );
        } catch (\Throwable $e) {
            // Silenciosamente ignora erros
        }
    }

    /**
     * Handle o evento de cache miss (chave não encontrada).
     */
    public function handleCacheMissed(CacheMissed $event): void
    {
        if (env('LONOMIA_ENABLED', true) == false) {
            return;
        }

        try {
            $lonomia = App::make(LonomiaService::class);
            
            // Calcula o tempo de execução se houver início registrado
            $executionTime = $this->calculateExecutionTime('get_' . $event->key);
            
            $lonomia->addCacheOperation(
                operation: 'get',
                key: $event->key,
                value: null,
                executionTime: $executionTime,
                success: true,
                metadata: [
                    'store' => $event->store ?? null,
                    'hit' => false,
                ]
            );
        } catch (\Throwable $e) {
            // Silenciosamente ignora erros
        }
    }

    /**
     * Handle o evento de escrita no cache.
     */
    public function handleKeyWritten(KeyWritten $event): void
    {
        if (env('LONOMIA_ENABLED', true) == false) {
            return;
        }

        try {
            $lonomia = App::make(LonomiaService::class);
            
            // Calcula o tempo de execução se houver início registrado
            $executionTime = $this->calculateExecutionTime('put_' . $event->key);
            
            $lonomia->addCacheOperation(
                operation: 'put',
                key: $event->key,
                value: $this->serializeValue($event->value),
                executionTime: $executionTime,
                success: true,
                metadata: [
                    'store' => $event->store ?? null,
                    'seconds' => $event->seconds ?? null,
                    'tags' => $event->tags ?? null,
                ]
            );
        } catch (\Throwable $e) {
            // Silenciosamente ignora erros
        }
    }

    /**
     * Handle o evento de remoção do cache.
     */
    public function handleKeyForgotten(KeyForgotten $event): void
    {
        if (env('LONOMIA_ENABLED', true) == false) {
            return;
        }

        try {
            $lonomia = App::make(LonomiaService::class);
            
            // Calcula o tempo de execução se houver início registrado
            $executionTime = $this->calculateExecutionTime('forget_' . $event->key);
            
            $lonomia->addCacheOperation(
                operation: 'forget',
                key: $event->key,
                value: null,
                executionTime: $executionTime,
                success: true,
                metadata: [
                    'store' => $event->store ?? null,
                ]
            );
        } catch (\Throwable $e) {
            // Silenciosamente ignora erros
        }
    }

    /**
     * Calcula o tempo de execução de uma operação se houver início registrado.
     */
    private function calculateExecutionTime(string $operationKey): ?float
    {
        if (isset(self::$operationStartTimes[$operationKey])) {
            $startTime = self::$operationStartTimes[$operationKey];
            unset(self::$operationStartTimes[$operationKey]);
            return microtime(true) - $startTime;
        }
        
        return null;
    }

    /**
     * Serializa um valor para armazenamento seguro.
     */
    private function serializeValue($value)
    {
        if ($value === null || is_scalar($value)) {
            return $value;
        }

        if (is_array($value) || is_object($value)) {
            $serialized = json_encode($value);
            if ($serialized !== false) {
                return strlen($serialized) > 1000 
                    ? substr($serialized, 0, 1000) . '...[truncado]'
                    : $serialized;
            }
        }

        return '[valor não serializável: ' . gettype($value) . ']';
    }
}
