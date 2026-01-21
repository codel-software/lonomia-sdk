<?php

namespace CodelSoftware\LonomiaSdk\Listeners;

use Illuminate\Log\Events\MessageLogged;
use CodelSoftware\LonomiaSdk\Services\LonomiaService;
use Illuminate\Support\Facades\App;

class LogListener
{
    /**
     * Flag para evitar recursão infinita caso o próprio Lonomia tente fazer log.
     */
    private static bool $processing = false;

    /**
     * Handle o evento de log do Laravel.
     * 
     * Intercepta todos os logs registrados via logger() e os adiciona ao Lonomia
     * para rastreamento e monitoramento.
     */
    public function handle(MessageLogged $event): void
    {
        if (env('LONOMIA_ENABLED', true) == false) {
            return;
        }

        // Evita recursão infinita
        if (self::$processing) {
            return;
        }

        try {
            self::$processing = true;
            $lonomia = App::make(LonomiaService::class);
            
            // Converte o nível do log para o método correspondente do Lonomia
            // O nível pode vir como string ('info') ou como número (200) do PSR-3
            $level = is_string($event->level) ? strtolower($event->level) : $this->convertPsrLevelToString($event->level);
            $message = $event->message;
            $context = $event->context;
            
            // Chama o método correspondente no Lonomia
            match ($level) {
                'emergency' => $lonomia->emergency($message, $context),
                'alert' => $lonomia->alert($message, $context),
                'critical' => $lonomia->critical($message, $context),
                'error' => $lonomia->error($message, $context),
                'warning' => $lonomia->warning($message, $context),
                'notice' => $lonomia->notice($message, $context),
                'info' => $lonomia->info($message, $context),
                'debug' => $lonomia->debug($message, $context),
                default => $lonomia->info($message, $context),
            };
        } catch (\Throwable $e) {
            // Silenciosamente ignora erros para não afetar o fluxo da aplicação
            // Log apenas em desenvolvimento
            if (env('APP_DEBUG', false)) {
                \Illuminate\Support\Facades\Log::debug('Erro ao capturar log no Lonomia: ' . $e->getMessage(), [
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        } finally {
            self::$processing = false;
        }
    }

    /**
     * Converte nível PSR-3 numérico para string.
     * 
     * @param int $level Nível numérico do PSR-3
     * @return string Nível como string
     */
    private function convertPsrLevelToString(int $level): string
    {
        return match ($level) {
            600 => 'emergency', // \Psr\Log\LogLevel::EMERGENCY
            550 => 'alert',     // \Psr\Log\LogLevel::ALERT
            500 => 'critical',  // \Psr\Log\LogLevel::CRITICAL
            400 => 'error',     // \Psr\Log\LogLevel::ERROR
            300 => 'warning',   // \Psr\Log\LogLevel::WARNING
            250 => 'notice',    // \Psr\Log\LogLevel::NOTICE
            200 => 'info',      // \Psr\Log\LogLevel::INFO
            100 => 'debug',     // \Psr\Log\LogLevel::DEBUG
            default => 'info',
        };
    }
}
