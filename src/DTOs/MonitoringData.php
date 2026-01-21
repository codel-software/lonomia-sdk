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
     *
     * @param array $data Array com os dados de monitoramento
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $queries = [];
        if (isset($data['queries']) && is_array($data['queries'])) {
            foreach ($data['queries'] as $query) {
                $queries[] = QueryData::fromArray($query);
            }
        }

        $externalRequests = [];
        if (isset($data['external_requests']) && is_array($data['external_requests'])) {
            foreach ($data['external_requests'] as $request) {
                $externalRequests[] = ExternalRequestData::fromArray($request);
            }
        }

        $apm = [];
        if (isset($data['apm']) && is_array($data['apm'])) {
            foreach ($data['apm'] as $tagName => $tagData) {
                $apm[$tagName] = ApmTagData::fromArray($tagData);
            }
        }

        $logs = [];
        if (isset($data['logs']) && is_array($data['logs'])) {
            foreach ($data['logs'] as $log) {
                $logs[] = LogEntryData::fromArray($log);
            }
        }

        $cache = [];
        if (isset($data['cache']) && is_array($data['cache'])) {
            foreach ($data['cache'] as $cacheOp) {
                $cache[] = CacheOperationData::fromArray($cacheOp);
            }
        }

        $jobs = [];
        if (isset($data['jobs']) && is_array($data['jobs'])) {
            foreach ($data['jobs'] as $job) {
                $jobs[] = JobData::fromArray($job);
            }
        }

        return new self(
            trackingId: $data['tracking_id'] ?? '',
            request: RequestData::fromArray($data['request'] ?? []),
            performance: PerformanceData::fromArray($data['performance'] ?? []),
            response: isset($data['response']) && $data['response'] !== null
                ? ResponseData::fromArray($data['response'])
                : null,
            queries: $queries,
            httpRequests: $data['http_requests'] ?? [],
            externalRequests: $externalRequests,
            apm: $apm,
            logs: $logs,
            cache: $cache,
            jobs: $jobs,
            exception: isset($data['exception']) && $data['exception'] !== null
                ? ExceptionData::fromArray($data['exception'])
                : null,
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
