<?php

namespace CodelSoftware\LonomiaSdk\Support;

use Illuminate\Support\Facades\Log;

/**
 * Helper para executar tarefas de forma deferida (após o envio da resposta HTTP).
 *
 * Verifica automaticamente se a versão do Laravel suporta defer() (11.23.0+)
 * e aplica o defer quando disponível, ou executa de forma síncrona como fallback.
 *
 * Garante que a experiência do cliente não seja afetada, executando tarefas
 * não-críticas (logs, métricas, telemetria) após o envio da resposta.
 */
class DeferredHelper
{
    /**
     * Versão mínima do Laravel que suporta defer().
     */
    private const MIN_LARAVEL_VERSION = '11.23.0';

    /**
     * Verifica se a versão do Laravel suporta defer().
     */
    public static function supportsDefer(): bool
    {
        $laravelVersion = app()->version();

        // Laravel 12+ sempre suporta defer()
        if (version_compare($laravelVersion, '12.0.0', '>=')) {
            return true;
        }

        // Laravel 11.23.0+ suporta defer()
        if (version_compare($laravelVersion, '11.0.0', '>=') &&
            version_compare($laravelVersion, self::MIN_LARAVEL_VERSION, '>=')) {
            return true;
        }

        return false;
    }

    /**
     * Executa uma closure de forma deferida se disponível, ou síncrona como fallback.
     *
     * Todas as exceções são capturadas e logadas para não afetar o ciclo de request.
     *
     * @param  string  $name  Nome identificador da tarefa (para logs)
     * @param  callable  $callback  Closure a ser executada
     */
    public static function run(string $name, callable $callback): void
    {
        if (self::supportsDefer()) {
            try {
                // Usa defer() do Laravel diretamente - verificação de versão garante que está disponível
                // Se houver algum problema, o catch vai capturar e fazer fallback
                \Illuminate\Support\defer(function () use ($name, $callback) {
                    self::executeDeferred($name, $callback);
                });
            } catch (\Throwable $e) {
                // Se o defer() falhar ao registrar (função não existe ou erro), executa de forma síncrona
                Log::warning("lonomia.defer.registration_failed:{$name}", [
                    'error' => $e->getMessage(),
                    'fallback' => 'sync',
                ]);
                self::executeSync($name, $callback);
            }
        } else {
            // Fallback: executa de forma síncrona se defer() não estiver disponível
            self::executeSync($name, $callback);
        }
    }

    /**
     * Executa a closure deferida com monitoramento de performance.
     */
    private static function executeDeferred(string $name, callable $callback): void
    {
        try {
            $startTime = microtime(true);
            $callback();
            $duration = (microtime(true) - $startTime) * 1000; // em ms

            // Log warning se a tarefa demorar mais que 100ms (recomendado para Octane)
            if ($duration > 100) {
                Log::warning('lonomia.defer.slow_task', [
                    'name' => $name,
                    'duration_ms' => round($duration, 2),
                    'threshold_ms' => 100,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning("lonomia.defer.failed:{$name}", [
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }

    /**
     * Executa a closure de forma síncrona (fallback).
     */
    private static function executeSync(string $name, callable $callback): void
    {
        try {
            $callback();
        } catch (\Throwable $e) {
            Log::warning("lonomia.defer.sync_failed:{$name}", [
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }
}
