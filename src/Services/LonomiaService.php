<?php

namespace CodelSoftware\LonomiaSdk\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

class LonomiaService
{
    protected $userContext = [];
    protected $apmTags = [];
    protected $logs = [];
    protected $queries = [];

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
    private function addLog(string $level, string $message, array $context = []): void
    {
        $this->logs[] = [
            'timestamp' => microtime(true),
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];
    }

    // Métodos para diferentes níveis de log
    public function emergency(string $message, array $context = []): void
    {
        $this->addLog('emergency', $message, $context);
    }

    public function alert(string $message, array $context = []): void
    {
        $this->addLog('alert', $message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->addLog('critical', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->addLog('error', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->addLog('warning', $message, $context);
    }

    public function notice(string $message, array $context = []): void
    {
        $this->addLog('notice', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->addLog('info', $message, $context);
    }

    public function debug(string $message, array $context = []): void
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
     * @param array $data
     */
    public function logPerformanceData(array $data)
    {
        // Aqui você implementa o envio dos dados para o servidor de monitoramento.
        // Exemplo fictício de envio:
        $data['project_token'] = config('lonomia.api_key');
        $data['image_tag'] = env('LOMONIA_IMAGE_TAG');
        $data['app_route'] = $this->getAppRoute($data['request']['url']);
        Http::post('http://127.0.0.1' . '/api/monitoring', $data);
    }

    
    public function getAppRoute($url)
    {
        // 1. Remover protocolo e domínio (http://127.0.0.1/)
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
}
