<?php

namespace CodelSoftware\LonomiaSdk\Services;

use CodelSoftware\LonomiaSdk\DTOs\PerformanceData;
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

    /**
     * Define o contexto do usuário.
     *
     * @param array $context
     */
    public function setUserContext(array $context): void
    {
        $this->userContext = $context;
    }

    /**
     * Adiciona um evento ao log.
     *
     * @param string $level
     * @param string $message
     * @param array $context
     */
    private function addLog(string $level, string $message, $context = null): void
    {
        if($context != null){
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
    public function emergency(string $message,  $context = null): void
    {
        
        $this->addLog('emergency', $message, $context);
    }

    public function alert(string $message,  $context = null): void
    {
        
        $this->addLog('alert', $message, $context);
    }

    public function critical(string $message,  $context = null): void
    {
        $this->addLog('critical', $message, $context);
    }

    public function error(string $message,  $context = null): void
    {
        $this->addLog('error', $message, $context);
    }

    public function warning(string $message,  $context = null): void
    {
        $this->addLog('warning', $message, $context);
    }

    public function notice(string $message,  $context = null): void
    {
        $this->addLog('notice', $message, $context);
    }

    public function info(string $message,  $context = null): void
    {
        $this->addLog('info', $message, $context);
    }

    public function debug(string $message,  $context = null): void
    {
        $this->addLog('debug', $message, $context);
    }

    /**
     * Inicia a medição de um bloco de código.
     *
     * @param string $tag
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
     *
     * @param string $tag
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
     *
     * @return array
     */
    public function getApmData(): array
    {
        return $this->apmTags;
    }

    /**
     * Adiciona uma query ao registro.
     *
     * @param string $sql
     * @param array $bindings
     * @param float $time
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
     *
     * @return array
     */
    public function getQueries(): array
    {
        return $this->queries;
    }

    /**
     * Envia os dados finais para o servidor de monitoramento.
     * 
     * Converte dados de performance de array para objeto PerformanceData para facilitar debug
     * e garantir type safety. Mantém compatibilidade reversa aceitando arrays.
     *
     * @param array $data Array contendo os dados de monitoramento, incluindo 'performance'
     */
    public function logPerformanceData(array $data)
    {
        // Converte array de performance para objeto PerformanceData se necessário
        if (isset($data['performance']) && is_array($data['performance'])) {
            $performanceData = new PerformanceData($data['performance']);
            // Converte de volta para array para serialização HTTP
            $data['performance'] = $performanceData->toArray();
        }
        
        // Aqui você implementa o envio dos dados para o servidor de monitoramento.
        // Exemplo fictício de envio:
        $data['project_token'] = config('lonomia.api_key');
        $data['image_tag'] = env('LOMONIA_IMAGE_TAG');
        $data['app_route'] = $this->getAppRoute($data['request']['url']);
        
        // Adiciona external_requests ao payload (null se vazio)
        $data['external_requests'] = !empty($this->externalRequests) ? $this->externalRequests : null;
        
        $url = env('LOMONIA_API_URL', 'https://lonomia.com.br');
        Http::post($url . '/api/monitoring', $data);
        //Http::post('http://127.0.0.1' . '/api/monitoring', $data);
        
        // Limpa external_requests após envio
        $this->clearExternalRequests();
    }

    
    public function getAppRoute($url)
    {
        // 1. Remover protocolo e domínio (https://lonomia.com.br/)
        $parsedUrl = parse_url($url);
        $path = $parsedUrl['path'] ?? '/';
    
        // 2. Remover a barra inicial para padronizar com as rotas do Laravel
        $normalizedPath = ltrim($path, '/');
    
        // 3. Normalizar números para {id}
        $normalizedPath = preg_replace('/\b\d+\b/', '{id}', $normalizedPath);
    
        // 4. Obter todas as rotas registradas no Laravel
        $routes = collect(Route::getRoutes())->map(function ($route) {
            return ltrim($route->uri(), '/'); // Também removemos a barra inicial das rotas
        });
        // 5. Comparar a URL normalizada com as rotas registradas
        foreach ($routes as $route) {
            // Criar regex para comparar (substitui {param} por regex)
            $pattern = preg_replace('/\{[^}]+\}/', '([^/]+)', $route);
            $pattern = "#^" . $pattern . "$#";
    
            // Verifica se a URL normalizada bate com a rota
            if (preg_match($pattern, $normalizedPath)) {
                if($route ==  ""){
                    return "/";
                }
                return $route; // Retorna a rota correspondente
            }
        }
    
        return null; // Nenhuma rota correspondente encontrada
    }
    


    /**
     * Retorna todos os logs registrados.
     *
     * @return array
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
     * @param string $url URL completa da API externa
     * @param string $method Método HTTP (GET, POST, PUT, DELETE, etc)
     * @param array|null $requestHeaders Headers da requisição
     * @param mixed|null $requestBody Corpo da requisição (array, string, etc)
     * @param int|null $statusCode Status HTTP da resposta
     * @param array|null $responseHeaders Headers da resposta
     * @param mixed|null $responseBody Corpo da resposta
     * @param float|null $executionTime Tempo de execução em segundos
     * @param bool $success Indica se a chamada foi bem-sucedida
     * @param string|null $errorMessage Mensagem de erro se houver
     * @return void
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
        // Timestamp atual (Unix timestamp com microsegundos)
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
     *
     * @return array
     */
    public function getExternalRequests(): array
    {
        return $this->externalRequests;
    }

    /**
     * Limpa o array de chamadas HTTP externas.
     *
     * Útil para resetar após enviar os dados ao servidor, evitando acúmulo de memória entre requisições.
     *
     * @return void
     */
    public function clearExternalRequests(): void
    {
        $this->externalRequests = [];
    }

    /**
     * Adiciona uma requisição HTTP ao registro.
     *
     * @param string $method
     * @param string $url
     * @param array $options
     * @param float $startTime
     * @param float|null $endTime
     * @param int|null $statusCode
     * @param array|null $responseHeaders
     * @param string|null $responseBody
     * @param \Throwable|null $exception
     */
    public function addHttpRequest(
        string $method,
        string $url,
        array $options = [],
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
     *
     * @return array
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
                    $part['contents'] = '[arquivo binário - ' . strlen($part['contents']) . ' bytes]';
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
            return substr($body, 0, $maxLength) . '...[truncado ' . (strlen($body) - $maxLength) . ' bytes]';
        }
        
        return $body;
    }
}
