<?php

namespace CodelSoftware\LonomiaSdk\DTOs;

/**
 * Data Transfer Object principal para dados de monitoramento.
 * 
 * Agrupa todos os dados coletados durante uma requisição HTTP, incluindo
 * requisição, resposta, performance, queries, logs, cache, jobs e exceções.
 */
class MonitoringData
{
    /**
     * @param string $trackingId ID de rastreamento da requisição
     * @param RequestData $request Dados da requisição HTTP
     * @param PerformanceData $performance Dados de performance
     * @param ResponseData|null $response Dados da resposta HTTP (opcional)
     * @param QueryData[] $queries Array de queries SQL executadas
     * @param array $httpRequests Array de requisições HTTP internas
     * @param ExternalRequestData[] $externalRequests Array de requisições HTTP externas
     * @param array<string, ApmTagData> $apm Tags APM indexadas por nome
     * @param LogEntryData[] $logs Array de entradas de log
     * @param CacheOperationData[] $cache Array de operações de cache
     * @param JobData[] $jobs Array de jobs executados
     * @param ExceptionData|null $exception Dados de exceção (se houver)
     */
    public function __construct(
        public string $trackingId,
        public RequestData $request,
        public PerformanceData $performance,
        public ?ResponseData $response = null,
        public array $queries = [],
        public array $httpRequests = [],
        public array $externalRequests = [],
        public array $apm = [],
        public array $logs = [],
        public array $cache = [],
        public array $jobs = [],
        public ?ExceptionData $exception = null,
    ) {}

    /**
     * Cria uma instância de MonitoringData a partir de um array.
     * 
     * Converte automaticamente todos os sub-arrays para seus respectivos DTOs.
     * Inclui tratamento de erros robusto para garantir que nunca trave a plataforma.
     *
     * @param array $data Array com os dados de monitoramento
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $queries = [];
        if (isset($data['queries']) && is_array($data['queries'])) {
            foreach ($data['queries'] as $query) {
                try {
                    if (is_array($query)) {
                        $queries[] = QueryData::fromArray($query);
                    }
                } catch (\Throwable $e) {
                    // Ignora queries inválidas para não travar a plataforma
                    continue;
                }
            }
        }

        $externalRequests = [];
        if (isset($data['external_requests']) && is_array($data['external_requests'])) {
            foreach ($data['external_requests'] as $request) {
                try {
                    if (is_array($request)) {
                        $externalRequests[] = ExternalRequestData::fromArray($request);
                    }
                } catch (\Throwable $e) {
                    // Ignora requisições externas inválidas para não travar a plataforma
                    continue;
                }
            }
        }

        $apm = [];
        if (isset($data['apm']) && is_array($data['apm'])) {
            foreach ($data['apm'] as $tagName => $tagData) {
                try {
                    if (is_array($tagData) && is_string($tagName)) {
                        $apm[$tagName] = ApmTagData::fromArray($tagData);
                    }
                } catch (\Throwable $e) {
                    // Ignora tags APM inválidas para não travar a plataforma
                    continue;
                }
            }
        }

        $logs = [];
        if (isset($data['logs']) && is_array($data['logs'])) {
            foreach ($data['logs'] as $log) {
                try {
                    if (is_array($log)) {
                        $logs[] = LogEntryData::fromArray($log);
                    }
                } catch (\Throwable $e) {
                    // Ignora logs inválidos para não travar a plataforma
                    continue;
                }
            }
        }

        $cache = [];
        if (isset($data['cache']) && is_array($data['cache'])) {
            foreach ($data['cache'] as $cacheOp) {
                try {
                    if (is_array($cacheOp)) {
                        $cache[] = CacheOperationData::fromArray($cacheOp);
                    }
                } catch (\Throwable $e) {
                    // Ignora operações de cache inválidas para não travar a plataforma
                    continue;
                }
            }
        }

        $jobs = [];
        if (isset($data['jobs']) && is_array($data['jobs'])) {
            foreach ($data['jobs'] as $job) {
                try {
                    if (is_array($job)) {
                        $jobs[] = JobData::fromArray($job);
                    }
                } catch (\Throwable $e) {
                    // Ignora jobs inválidos para não travar a plataforma
                    continue;
                }
            }
        }

        try {
            $request = RequestData::fromArray($data['request'] ?? []);
        } catch (\Throwable $e) {
            // Cria um RequestData vazio em caso de erro
            $request = RequestData::fromArray([]);
        }

        try {
            $performance = PerformanceData::fromArray($data['performance'] ?? []);
        } catch (\Throwable $e) {
            // Cria um PerformanceData vazio em caso de erro
            $performance = PerformanceData::fromArray([]);
        }

        $response = null;
        if (isset($data['response']) && $data['response'] !== null) {
            try {
                if (is_array($data['response'])) {
                    $response = ResponseData::fromArray($data['response']);
                }
            } catch (\Throwable $e) {
                // Ignora resposta inválida, mantém como null
                $response = null;
            }
        }

        $exception = null;
        if (isset($data['exception']) && $data['exception'] !== null) {
            try {
                if (is_array($data['exception'])) {
                    $exception = ExceptionData::fromArray($data['exception']);
                }
            } catch (\Throwable $e) {
                // Ignora exceção inválida, mantém como null
                $exception = null;
            }
        }

        return new self(
            trackingId: (string) ($data['tracking_id'] ?? ''),
            request: $request,
            performance: $performance,
            response: $response,
            queries: $queries,
            httpRequests: $data['http_requests'] ?? [],
            externalRequests: $externalRequests,
            apm: $apm,
            logs: $logs,
            cache: $cache,
            jobs: $jobs,
            exception: $exception,
        );
    }

    /**
     * Converte o objeto para array.
     * 
     * Útil para serialização JSON ou compatibilidade com código legado.
     *
     * @return array
     */
    public function toArray(): array
    {
        $result = [
            'tracking_id' => $this->trackingId,
            'request' => $this->request->toArray(),
            'performance' => $this->performance->toArray(),
        ];

        if ($this->response !== null) {
            $result['response'] = $this->response->toArray();
        }

        $result['queries'] = array_map(fn($q) => $q->toArray(), $this->queries);
        $result['http_requests'] = $this->httpRequests;
        
        $result['external_requests'] = array_map(
            fn($r) => $r->toArray(),
            $this->externalRequests
        );

        $result['apm'] = [];
        foreach ($this->apm as $tagName => $tagData) {
            $result['apm'][$tagName] = $tagData->toArray();
        }

        $result['logs'] = array_map(fn($l) => $l->toArray(), $this->logs);
        $result['cache'] = array_map(fn($c) => $c->toArray(), $this->cache);
        $result['jobs'] = array_map(fn($j) => $j->toArray(), $this->jobs);

        if ($this->exception !== null) {
            $result['exception'] = $this->exception->toArray();
        }

        return $result;
    }
}
