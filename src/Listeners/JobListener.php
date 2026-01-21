<?php

namespace CodelSoftware\LonomiaSdk\Listeners;

use Illuminate\Queue\Events\JobQueued;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobFailed;
use CodelSoftware\LonomiaSdk\Services\LonomiaService;
use Illuminate\Support\Facades\App;

class JobListener
{
    /**
     * Armazena os tempos de início dos jobs para calcular duração.
     */
    private static array $jobStartTimes = [];

    /**
     * Handle o evento de job enviado para a fila.
     */
    public function handleJobQueued(JobQueued $event): void
    {
        if (env('LONOMIA_ENABLED', true) == false) {
            return;
        }

        try {
            $lonomia = App::make(LonomiaService::class);
            
            $jobClass = get_class($event->job);
            $jobId = $this->getJobId($event->job);
            $connection = $event->connectionName ?? null;
            $queue = $this->getJobQueue($event->job);
            
            // Serializa o payload do job (limitado para evitar payloads muito grandes)
            $payload = $this->serializeJobPayload($event->job);
            
            $lonomia->addJob(
                status: 'queued',
                jobClass: $jobClass,
                jobId: $jobId,
                connection: $connection,
                queue: $queue,
                payload: $payload
            );
        } catch (\Throwable $e) {
            // Silenciosamente ignora erros
            if (env('APP_DEBUG', false)) {
                \Illuminate\Support\Facades\Log::debug('Erro ao capturar JobQueued no Lonomia: ' . $e->getMessage());
            }
        }
    }

    /**
     * Handle o evento de job começando a ser processado.
     */
    public function handleJobProcessing(JobProcessing $event): void
    {
        if (env('LONOMIA_ENABLED', true) == false) {
            return;
        }

        try {
            $lonomia = App::make(LonomiaService::class);
            
            $jobId = $this->getJobId($event->job);
            $jobClass = $this->getJobClass($event->job);
            $connection = $event->connectionName ?? null;
            $queue = $this->getJobQueue($event->job);
            
            // Armazena o tempo de início
            self::$jobStartTimes[$jobId] = microtime(true);
            
            $payload = $this->serializeJobPayload($event->job);
            
            $lonomia->addJob(
                status: 'processing',
                jobClass: $jobClass,
                jobId: $jobId,
                connection: $connection,
                queue: $queue,
                payload: $payload
            );
        } catch (\Throwable $e) {
            // Silenciosamente ignora erros
            if (env('APP_DEBUG', false)) {
                \Illuminate\Support\Facades\Log::debug('Erro ao capturar JobProcessing no Lonomia: ' . $e->getMessage());
            }
        }
    }

    /**
     * Handle o evento de job processado com sucesso.
     */
    public function handleJobProcessed(JobProcessed $event): void
    {
        if (env('LONOMIA_ENABLED', true) == false) {
            return;
        }

        try {
            $lonomia = App::make(LonomiaService::class);
            
            $jobId = $this->getJobId($event->job);
            $jobClass = $this->getJobClass($event->job);
            $connection = $event->connectionName ?? null;
            $queue = $this->getJobQueue($event->job);
            
            // Calcula o tempo de execução
            $executionTime = null;
            if (isset(self::$jobStartTimes[$jobId])) {
                $executionTime = microtime(true) - self::$jobStartTimes[$jobId];
                unset(self::$jobStartTimes[$jobId]);
            }
            
            $payload = $this->serializeJobPayload($event->job);
            
            $lonomia->addJob(
                status: 'processed',
                jobClass: $jobClass,
                jobId: $jobId,
                connection: $connection,
                queue: $queue,
                executionTime: $executionTime,
                payload: $payload
            );
        } catch (\Throwable $e) {
            // Silenciosamente ignora erros
            if (env('APP_DEBUG', false)) {
                \Illuminate\Support\Facades\Log::debug('Erro ao capturar JobProcessed no Lonomia: ' . $e->getMessage());
            }
        }
    }

    /**
     * Handle o evento de job que falhou.
     */
    public function handleJobFailed(JobFailed $event): void
    {
        if (env('LONOMIA_ENABLED', true) == false) {
            return;
        }

        try {
            $lonomia = App::make(LonomiaService::class);
            
            $jobId = $this->getJobId($event->job);
            $jobClass = $this->getJobClass($event->job);
            $connection = $event->connectionName ?? null;
            $queue = $this->getJobQueue($event->job);
            $exception = $event->exception ?? null;
            
            // Calcula o tempo de execução
            $executionTime = null;
            if (isset(self::$jobStartTimes[$jobId])) {
                $executionTime = microtime(true) - self::$jobStartTimes[$jobId];
                unset(self::$jobStartTimes[$jobId]);
            }
            
            $payload = $this->serializeJobPayload($event->job);
            
            $lonomia->addJob(
                status: 'failed',
                jobClass: $jobClass,
                jobId: $jobId,
                connection: $connection,
                queue: $queue,
                executionTime: $executionTime,
                exception: $exception,
                payload: $payload
            );
        } catch (\Throwable $e) {
            // Silenciosamente ignora erros
            if (env('APP_DEBUG', false)) {
                \Illuminate\Support\Facades\Log::debug('Erro ao capturar JobFailed no Lonomia: ' . $e->getMessage());
            }
        }
    }

    /**
     * Obtém o ID do job.
     */
    private function getJobId($job): ?string
    {
        if (method_exists($job, 'getJobId')) {
            return $job->getJobId();
        }
        
        // Tenta obter do payload
        try {
            if (method_exists($job, 'payload')) {
                $payload = $job->payload();
                return $payload['uuid'] ?? $payload['id'] ?? null;
            }
        } catch (\Throwable $e) {
            // Ignora
        }
        
        return spl_object_hash($job);
    }

    /**
     * Obtém o nome da fila do job.
     */
    private function getJobQueue($job): ?string
    {
        if (method_exists($job, 'getQueue')) {
            return $job->getQueue();
        }
        
        try {
            if (method_exists($job, 'payload')) {
                $payload = $job->payload();
                return $payload['queue'] ?? null;
            }
        } catch (\Throwable $e) {
            // Ignora
        }
        
        return null;
    }

    /**
     * Obtém o nome da classe do job.
     */
    private function getJobClass($job): string
    {
        // Tenta obter a classe do job
        if (method_exists($job, 'resolveName')) {
            return $job->resolveName();
        }
        
        if (method_exists($job, 'getName')) {
            return $job->getName();
        }
        
        // Tenta obter do payload
        try {
            if (method_exists($job, 'payload')) {
                $payload = $job->payload();
                if (isset($payload['displayName'])) {
                    return $payload['displayName'];
                }
                
                if (isset($payload['job'])) {
                    return $payload['job'];
                }
            }
        } catch (\Throwable $e) {
            // Ignora erros
        }
        
        return get_class($job);
    }

    /**
     * Serializa o payload do job de forma segura.
     */
    private function serializeJobPayload($job): ?array
    {
        try {
            if (method_exists($job, 'payload')) {
                $payload = $job->payload();
                
                // Remove dados sensíveis ou muito grandes
                if (isset($payload['data']['command'])) {
                    // Para jobs serializados, limita o tamanho
                    $command = $payload['data']['command'];
                    if (is_string($command) && strlen($command) > 1000) {
                        $payload['data']['command'] = substr($command, 0, 1000) . '...[truncado]';
                    }
                }
                
                return $payload;
            }
        } catch (\Throwable $e) {
            // Ignora erros de serialização
        }
        
        return null;
    }
}
