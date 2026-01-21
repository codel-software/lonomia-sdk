<?php

namespace CodelSoftware\LonomiaSdk\Services;

use CodelSoftware\LonomiaSdk\DTOs\MonitoringData;
use CodelSoftware\LonomiaSdk\Support\DeferredHelper;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

class LonomiaService
{
    protected $userContext = [];

    protected $apmTags = [];

    protected $logs = [];

    protected $queries = [];

    protected $httpRequests = [];

    protected $externalRequests = [];

    protected $cacheOperations = [];

    protected $jobs = [];

    /**
     * Define o contexto do usuário.
     */
    public function setUserContext(array $context): void
    {
        $this->userContext = $context;
    }

    /**
     * Adiciona um evento ao log.
     *
     * @param  array  $context
     */
    private function addLog(string $level, string $message, $context = null): void
    {
        if ($context != null) {
            $context = json_encode($context);
        }
        $this->logs[] = [
            'timestamp' => microtime(true),
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];
    }

    // Métodos para diferentes níveis de log
    public function emergency(string $message, $context = null): void
    {

        $this->addLog('emergency', $message, $context);
    }

    public function alert(string $message, $context = null): void
    {

        $this->addLog('alert', $message, $context);
    }

    public function critical(string $message, $context = null): void
    {
        $this->addLog('critical', $message, $context);
    }

    public function error(string $message, $context = null): void
    {
        $this->addLog('error', $message, $context);
    }

    public function warning(string $message, $context = null): void
    {
        $this->addLog('warning', $message, $context);
    }

    public function notice(string $message, $context = null): void
    {
        $this->addLog('notice', $message, $context);
    }

    public function info(string $message, $context = null): void
    {
        $this->addLog('info', $message, $context);
    }

    public function debug(string $message, $context = null): void
    {
        $this->addLog('debug', $message, $context);
    }

    /**
     * Inicia a medição de um bloco de código.
     */
    public function startTag(string $tag): void
    {
        $this->apmTags[$tag] = [
            'start' => microtime(true),
            'end' => null,
            'duration' => null,
        ];
    }

    /**
     * Finaliza a medição de um bloco de código e calcula a duração.
     */
    public function endTag(string $tag): void
    {
        if (isset($this->apmTags[$tag])) {
            $this->apmTags[$tag]['end'] = microtime(true);
            $this->apmTags[$tag]['duration'] = $this->apmTags[$tag]['end'] - $this->apmTags[$tag]['start'];
        }
    }

    /**
     * Retorna os dados de APM.
     */
    public function getApmData(): array
    {
        return $this->apmTags;
    }

    /**
     * Adiciona uma query ao registro.
     */
    public function addQuery(string $sql, array $bindings, float $time): void
    {
        $this->queries[] = [
            'sql' => $sql,
            'bindings' => $bindings,
            'time' => $time,
        ];
    }

    /**
     * Retorna todas as queries registradas.
     */
    public function getQueries(): array
    {
        return $this->queries;
    }

    /**
     * Envia os dados finais para o servidor de monitoramento.
     *
     * Aceita MonitoringData tipado ou array (para compatibilidade).
     * Converte automaticamente arrays para MonitoringData usando fromArray().
     *
     * O envio é feito de forma deferida (após o envio da resposta HTTP) quando
     * o Laravel suporta defer() (11.23.0+), garantindo que a experiência do
     * cliente não seja afetada. Em versões anteriores, executa de forma síncrona.
     *
     * @param  MonitoringData|array  $data  Dados de monitoramento tipados ou array
     */
    public function logPerformanceData(MonitoringData|array $data): void
    {
        if (is_array($data)) {
            $data = MonitoringData::fromArray($data);
        }

        $payload = $data->toArray();
        $payload['project_token'] = config('lonomia.api_key');
        $payload['image_tag'] = env('LOMONIA_IMAGE_TAG');
        $payload['app_route'] = $this->getAppRoute($data->request->url);

        if (empty($payload['external_requests']) && ! empty($this->externalRequests)) {
            $payload['external_requests'] = $this->externalRequests;
        }

        $url = env('LOMONIA_API_URL', 'https://lonomia.com.br');
        $endpoint = $url.'/api/monitoring';

        // Executa o envio de forma deferida quando disponível
        DeferredHelper::run('lonomia.send_monitoring', function () use ($endpoint, $payload) {
            // Timeout baixo (1.5s) para não bloquear o worker em Octane
            Http::timeout(1.5)->post($endpoint, $payload);
        });

        $this->clearExternalRequests();
    }

    public function getAppRoute(string $url): ?string
    {
        $parsedUrl = parse_url($url);
        $path = $parsedUrl['path'] ?? '/';
        $normalizedPath = ltrim($path, '/');
        $normalizedPath = preg_replace('/\b\d+\b/', '{id}', $normalizedPath);

        $routes = collect(Route::getRoutes())->map(fn ($route) => ltrim($route->uri(), '/'));

        foreach ($routes as $route) {
            $pattern = preg_replace('/\{[^}]+\}/', '([^/]+)', $route);
            $pattern = "#^{$pattern}$#";

            if (preg_match($pattern, $normalizedPath)) {
                return $route === '' ? '/' : $route;
            }
        }

        return null;
    }

    /**
     * Retorna todos os logs registrados.
     */
    public function getLogs(): array
    {
        return $this->logs;
    }

    /**
     * Registra uma chamada HTTP externa realizada durante a requisição.
     *
     * Este método armazena informações sobre chamadas para APIs de terceiros, webhooks, ou qualquer serviço HTTP externo.
     * Os dados são enviados ao servidor Lonomia junto com os demais dados de monitoramento (queries, logs, APM tags).
     * Permite rastrear integrações externas, identificar falhas em serviços de terceiros e medir performance de APIs.
     *
     * @param  string  $url  URL completa da API externa
     * @param  string  $method  Método HTTP (GET, POST, PUT, DELETE, etc)
     * @param  array|null  $requestHeaders  Headers da requisição
     * @param  mixed|null  $requestBody  Corpo da requisição (array, string, etc)
     * @param  int|null  $statusCode  Status HTTP da resposta
     * @param  array|null  $responseHeaders  Headers da resposta
     * @param  mixed|null  $responseBody  Corpo da resposta
     * @param  float|null  $executionTime  Tempo de execução em segundos
     * @param  bool  $success  Indica se a chamada foi bem-sucedida
     * @param  string|null  $errorMessage  Mensagem de erro se houver
     */
    public function addExternalRequest(
        string $url,
        string $method = 'GET',
        ?array $requestHeaders = null,
        $requestBody = null,
        ?int $statusCode = null,
        ?array $responseHeaders = null,
        $responseBody = null,
        ?float $executionTime = null,
        bool $success = true,
        ?string $errorMessage = null
    ): void {
        $executedAt = microtime(true);

        $this->externalRequests[] = [
            'url' => $url,
            'method' => strtoupper($method),
            'request_headers' => $requestHeaders ?? [],
            'request_body' => $requestBody,
            'status_code' => $statusCode,
            'response_headers' => $responseHeaders ?? [],
            'response_body' => $responseBody,
            'execution_time' => $executionTime,
            'executed_at' => $executedAt,
            'success' => $success,
            'error_message' => $errorMessage,
        ];
    }

    /**
     * Retorna todas as chamadas HTTP externas registradas.
     */
    public function getExternalRequests(): array
    {
        return $this->externalRequests;
    }

    /**
     * Limpa o array de chamadas HTTP externas.
     *
     * Útil para resetar após enviar os dados ao servidor, evitando acúmulo de memória entre requisições.
     */
    public function clearExternalRequests(): void
    {
        $this->externalRequests = [];
    }

    /**
     * Adiciona uma requisição HTTP ao registro.
     */
    public function addHttpRequest(
        string $method,
        string $url,
        array $options,
        float $startTime,
        ?float $endTime = null,
        ?int $statusCode = null,
        ?array $responseHeaders = null,
        ?string $responseBody = null,
        ?\Throwable $exception = null
    ): void {
        $requestId = uniqid('http_', true);

        $this->httpRequests[$requestId] = [
            'method' => $method,
            'url' => $url,
            'headers' => $options['headers'] ?? [],
            'body' => $this->extractHttpBody($options),
            'start_time' => $startTime,
            'end_time' => $endTime,
            'duration' => $endTime !== null ? ($endTime - $startTime) : null,
            'status_code' => $statusCode,
            'response_headers' => $responseHeaders,
            'response_body' => $responseBody ? $this->limitResponseBody($responseBody) : null,
            'exception' => $exception ? [
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
            ] : null,
        ];
    }

    /**
     * Retorna todas as requisições HTTP registradas.
     */
    public function getHttpRequests(): array
    {
        return array_values($this->httpRequests);
    }

    /**
     * Limpa todas as requisições HTTP registradas.
     */
    public function clearHttpRequests(): void
    {
        $this->httpRequests = [];
    }

    /**
     * Extrai o body da requisição HTTP das opções.
     */
    private function extractHttpBody(array $options): ?array
    {
        if (isset($options['json'])) {
            return $options['json'];
        }

        if (isset($options['form_params'])) {
            return $options['form_params'];
        }

        if (isset($options['multipart'])) {
            // Remove arquivos do multipart para não enviar conteúdo binário
            return array_map(function ($part) {
                if (isset($part['contents']) && is_string($part['contents']) && strlen($part['contents']) > 1000) {
                    $part['contents'] = '[arquivo binário - '.strlen($part['contents']).' bytes]';
                }

                return $part;
            }, $options['multipart']);
        }

        if (isset($options['body'])) {
            $body = $options['body'];
            // Tenta decodificar JSON
            if (is_string($body)) {
                $decoded = json_decode($body, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $decoded;
                }

                return ['raw' => $this->limitResponseBody($body)];
            }

            return $body;
        }

        return null;
    }

    /**
     * Limita o tamanho do body da resposta para evitar payloads muito grandes.
     */
    private function limitResponseBody(?string $body, int $maxLength = 5000): ?string
    {
        if ($body === null) {
            return null;
        }

        if (strlen($body) > $maxLength) {
            return substr($body, 0, $maxLength).'...[truncado '.(strlen($body) - $maxLength).' bytes]';
        }

        return $body;
    }

    /**
     * Registra uma operação de cache/Redis realizada durante a requisição.
     *
     * Este método armazena informações sobre todas as operações de cache (get, put, forget, remember, etc.)
     * realizadas durante a requisição. Os dados são enviados ao servidor Lonomia junto com os demais
     * dados de monitoramento para rastrear uso de cache e identificar possíveis problemas de performance.
     *
     * @param  string  $operation  Tipo de operação (get, put, forget, remember, has, etc.)
     * @param  string|null  $key  Chave do cache
     * @param  mixed|null  $value  Valor armazenado/recuperado (será serializado se necessário)
     * @param  float|null  $executionTime  Tempo de execução em segundos
     * @param  bool  $success  Indica se a operação foi bem-sucedida
     * @param  string|null  $errorMessage  Mensagem de erro se houver
     * @param  array|null  $metadata  Metadados adicionais (TTL, tags, etc.)
     */
    public function addCacheOperation(
        string $operation,
        ?string $key = null,
        $value = null,
        ?float $executionTime = null,
        bool $success = true,
        ?string $errorMessage = null,
        ?array $metadata = null
    ): void {
        $executedAt = microtime(true);
        $serializedValue = null;
        if ($value !== null) {
            if (is_string($value) || is_numeric($value) || is_bool($value) || is_null($value)) {
                $serializedValue = $value;
            } elseif (is_array($value) || is_object($value)) {
                $serialized = json_encode($value);
                if ($serialized !== false) {
                    $serializedValue = strlen($serialized) > 1000
                        ? substr($serialized, 0, 1000).'...[truncado]'
                        : $serialized;
                } else {
                    $serializedValue = '[valor não serializável]';
                }
            } else {
                $serializedValue = '[tipo: '.gettype($value).']';
            }
        }

        $this->cacheOperations[] = [
            'operation' => strtolower($operation),
            'key' => $key,
            'value' => $serializedValue,
            'value_size' => $value !== null && is_string($value) ? strlen($value) : null,
            'execution_time' => $executionTime,
            'executed_at' => $executedAt,
            'success' => $success,
            'error_message' => $errorMessage,
            'metadata' => $metadata ?? [],
        ];
    }

    /**
     * Retorna todas as operações de cache registradas.
     */
    public function getCacheOperations(): array
    {
        return $this->cacheOperations;
    }

    /**
     * Limpa o array de operações de cache.
     *
     * Útil para resetar após enviar os dados ao servidor, evitando acúmulo de memória entre requisições.
     */
    public function clearCacheOperations(): void
    {
        $this->cacheOperations = [];
    }

    /**
     * Registra um evento de job (queued, processing, processed, failed).
     *
     * Este método armazena informações sobre jobs que são enviados para a fila ou executados.
     * Os dados são enviados ao servidor Lonomia junto com os demais dados de monitoramento.
     *
     * @param  string  $status  Status do job (queued, processing, processed, failed)
     * @param  string  $jobClass  Nome da classe do job
     * @param  string|null  $jobId  ID único do job na fila
     * @param  string|null  $connection  Nome da conexão da fila
     * @param  string|null  $queue  Nome da fila
     * @param  float|null  $executionTime  Tempo de execução em segundos (apenas para processed/failed)
     * @param  \Throwable|null  $exception  Exceção se o job falhou
     * @param  array|null  $payload  Payload do job (serializado)
     */
    public function addJob(
        string $status,
        string $jobClass,
        ?string $jobId = null,
        ?string $connection = null,
        ?string $queue = null,
        ?float $executionTime = null,
        ?\Throwable $exception = null,
        ?array $payload = null
    ): void {
        $executedAt = microtime(true);

        $jobData = [
            'status' => $status,
            'job_class' => $jobClass,
            'job_id' => $jobId,
            'connection' => $connection,
            'queue' => $queue,
            'execution_time' => $executionTime,
            'executed_at' => $executedAt,
            'payload' => $payload,
        ];

        if ($exception) {
            $jobData['exception'] = [
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ];
        }

        $this->jobs[] = $jobData;
    }

    /**
     * Retorna todas as operações de jobs registradas.
     */
    public function getJobs(): array
    {
        return $this->jobs;
    }

    /**
     * Limpa o array de jobs.
     *
     * Útil para resetar após enviar os dados ao servidor, evitando acúmulo de memória entre requisições.
     */
    public function clearJobs(): void
    {
        $this->jobs = [];
    }
}
