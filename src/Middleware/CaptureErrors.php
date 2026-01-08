<?php

namespace CodelSoftware\LonomiaSdk\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use CodelSoftware\LonomiaSdk\Services\LonomiaService;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class CaptureErrors
{
    protected $lonomia;
    
    protected static $streamData = [];

    public function __construct(LonomiaService $lonomia)
    {
        $this->lonomia = $lonomia;
    }

    public function handle(Request $request, Closure $next)
    {
        if(env('LONOMIA_ENABLED',true) == false){
            return $next($request);
        }
        
        try{

              // Nome do cookie de rastreamento
              $cookieName = 'tracking_id';

              // Verifica se o cookie já existe

            if( $request->cookie($cookieName) != null ){
                $trackingId = $request->cookie($cookieName);
            }else{
                $trackingId = 'anon_' . Str::uuid()->toString() . '_' . time();
                Cookie::queue(Cookie::make($cookieName, $trackingId, 525600)); // 1 ano
            }

             // Verifica se há um usuário logado
             $user = Auth::user();
              
             if ($user) {
                 // Se há usuário logado, cria um tracking_id baseado no ID do usuário
                 $trackingId = 'user_' . $user->id;
             }
            
            //dd($trackingId);
            // Define o contexto do usuário
            $this->lonomia->setUserContext(['tracking_id' => $trackingId]);

            // Início do monitoramento de performance
            $startTime = microtime(true);
            $startMemory = memory_get_usage();
            $queries = [];
            $exceptionData = null;
            $isStreamedResponse = false;

            // Captura as queries
            DB::listen(function ($query) use (&$queries) {
                $queries[] = [
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                    'time' => $query->time,
                    'executed_at' => now()->format('Y-m-d H:i:s.u'),
                ];

                // Adiciona a query ao APM
                $this->lonomia->addQuery($query->sql, $query->bindings, $query->time);
            });

        
                $this->lonomia->startTag('request-total');
                $response = $next($request);
                
                // Verifica se é uma StreamedResponse
                if ($response instanceof StreamedResponse) {
                    $isStreamedResponse = true;
                    $streamId = 'stream_' . $trackingId . '_' . time();
                    $streamStartTime = microtime(true);
                    
                    // Armazena dados do stream para captura posterior
                    // Clona o array de queries para evitar problemas com referências
                    $queriesCopy = $queries;
                    self::$streamData[$streamId] = [
                        'tracking_id' => $trackingId,
                        'start_time' => $startTime,
                        'stream_start_time' => $streamStartTime,
                        'request' => $request,
                        'queries' => $queriesCopy,
                        'start_memory' => $startMemory,
                        'lonomia' => $this->lonomia,
                        'cookie_name' => $cookieName,
                    ];
                    
                    // Envolve o callback do stream para capturar dados
                    $originalCallback = $response->getCallback();
                    $response->setCallback(function () use ($originalCallback, $streamId, $request) {
                            $streamCallbackStart = microtime(true);
                        $streamContent = '';
                        $streamQueries = [];
                        
                        try {
                            // Captura queries durante o stream
                            DB::listen(function ($query) use (&$streamQueries) {
                                $streamQueries[] = [
                                    'sql' => $query->sql,
                                    'bindings' => $query->bindings,
                                    'time' => $query->time,
                                    'executed_at' => now()->format('Y-m-d H:i:s.u'),
                                ];
                            });
                            
                            // Cria um wrapper para capturar o output usando output buffering
                            // Mesmo que o stream tente desabilitar, tentamos capturar o máximo possível
                            ob_start(function ($buffer) use (&$streamContent) {
                                $streamContent .= $buffer;
                                return $buffer; // Retorna o buffer para continuar o fluxo
                            }, 4096);
                            
                            // Executa o callback original
                            if ($originalCallback) {
                                call_user_func($originalCallback);
                            }
                            
                            // Finaliza e captura o buffer
                            if (ob_get_level() > 0) {
                                $bufferedContent = ob_get_clean();
                                if ($bufferedContent !== false) {
                                    $streamContent .= $bufferedContent;
                                }
                            }
                            
                        } catch (\Throwable $e) {
                            // Captura exceções durante o stream
                            if (isset(self::$streamData[$streamId])) {
                                self::$streamData[$streamId]['exception'] = $e;
                            }
                            throw $e;
                        } finally {
                            $streamCallbackEnd = microtime(true);
                            
                            // Registra dados do stream
                            if (isset(self::$streamData[$streamId])) {
                                // Adiciona queries capturadas durante o stream
                                if (!empty($streamQueries)) {
                                    self::$streamData[$streamId]['queries'] = array_merge(
                                        self::$streamData[$streamId]['queries'],
                                        $streamQueries
                                    );
                                }
                                
                                self::$streamData[$streamId]['stream_end_time'] = $streamCallbackEnd;
                                self::$streamData[$streamId]['stream_content'] = $streamContent;
                                self::$streamData[$streamId]['stream_duration'] = $streamCallbackEnd - $streamCallbackStart;
                                self::$streamData[$streamId]['end_time'] = microtime(true);
                                self::$streamData[$streamId]['end_memory'] = memory_get_usage();
                                self::$streamData[$streamId]['peak_memory'] = memory_get_peak_usage();
                                self::$streamData[$streamId]['complete'] = true;
                                
                                // Processa os dados do stream em background
                                $this->processStreamData($streamId);
                            }
                        }
                    });
                }
                
                $this->lonomia->endTag('request-total');

                $exceptionData = null;
                if(isset($response->exception)){
                    // Captura dados da exceção para serem enviados no log final
                    $exceptionData = [
                        'message' => $response->exception->getMessage(),
                        'trace' => $response->exception->getTrace(),
                        'code' => $response->exception->getCode(),
                        'file' => $response->exception->getFile(),
                        'line' => $response->exception->getLine(),
                        'file_snippet' => $this->getfileSnippet($response->exception)
                    ];
                }


            // Fim do monitoramento de performance
            $endTime = microtime(true);
            $endMemory = memory_get_usage();
            $peakMemory = memory_get_peak_usage();

            // Dados de performance
            $executionTime = $endTime - $startTime;
            
            $performanceData = [
                'execution_time' => $executionTime,
                'memory_start' => $startMemory,
                'memory_end' => $endMemory,
                'peak_memory' => $peakMemory,
                'is_streamed' => $isStreamedResponse,
            ];

            // Obtém o status code da resposta, se existir
            $statusCode = isset($response) ? $response->getStatusCode() : null;
            $isServerError = $statusCode && $statusCode >= 500 && $statusCode < 600;
            $isSlowRequest = $executionTime > 1.0;
            
            // Para StreamedResponse, o processamento será feito no callback do stream
            // Aqui processamos apenas respostas não-stream ou respostas com erro imediato
            if (!$isStreamedResponse) {
                $processa_request = (env('LONOMIA_REQUEST_ALL', false) == true) ? true : ($isSlowRequest || $isServerError);
                
                if ($processa_request) {
                    $responseBody = $this->getJsonResponseBody($response);
                    
                    $this->lonomia->logPerformanceData([
                        'tracking_id' => $trackingId,
                        'request' => [
                            'method' => $request->method(),
                            'url' => $request->fullUrl(),
                            'headers' => $request->headers->all(),
                            'body' => $this->getRequestBody($request),
                        ],
                        'response' => isset($response) ? [
                            'status' => $statusCode,
                            'headers' => $response->headers->all(),
                            'body' => $responseBody,
                        ] : null,
                        'performance' => $performanceData,
                        'queries' => $queries,
                        'http_requests' => $this->lonomia->getHttpRequests(), // Inclui requisições HTTP
                        'apm' => $this->lonomia->getApmData(),
                        'logs' => $this->lonomia->getLogs(),
                        'exception' => $exceptionData,
                    ]);
                }
            }

            // Garante que o cookie de rastreamento está presente na resposta
            if (!Cookie::hasQueued($cookieName)) {
                Cookie::queue(Cookie::make($cookieName, $trackingId, 525600));
            }
            
            // Limpa requisições HTTP após processamento (para não acumular memória)
            // Apenas para respostas não-stream, pois streams são processados separadamente
            if (!$isStreamedResponse) {
                $this->lonomia->clearHttpRequests();
                $this->lonomia->clearExternalRequests();
            }
        }catch(\Throwable $e){
            if(env('LONOMIA_ENABLED',true) == true){
                Log::error('Lonomia SDK Error: ' . $e->getMessage(), [
                    'exception' => $e,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                throw new \Exception($e->getMessage(), $e->getCode(), $e);
            }
        }
        return $response;
    }
    
    /**
     * Processa os dados do stream após a conclusão.
     */
    protected function processStreamData(string $streamId): void
    {
        if (!isset(self::$streamData[$streamId]) || !self::$streamData[$streamId]['complete']) {
            return;
        }
        
        $data = self::$streamData[$streamId];
        $trackingId = $data['tracking_id'];
        $startTime = $data['start_time'];
        $endTime = $data['end_time'];
        $streamStartTime = $data['stream_start_time'];
        $streamEndTime = $data['stream_end_time'];
        $executionTime = $endTime - $startTime;
        $streamDuration = $streamEndTime - $streamStartTime;
        
        $performanceData = [
            'execution_time' => $executionTime,
            'memory_start' => $data['start_memory'],
            'memory_end' => $data['end_memory'],
            'peak_memory' => $data['peak_memory'],
            'is_streamed' => true,
            'stream_duration' => $streamDuration,
        ];
        
        $isSlowRequest = $executionTime > 1.0;
        $processa_request = (env('LONOMIA_REQUEST_ALL', false) == true) || $isSlowRequest;
        
        if ($processa_request) {
            $responseBody = [
                'type' => 'stream',
                'content_length' => strlen($data['stream_content'] ?? ''),
                'content_preview' => substr($data['stream_content'] ?? '', 0, 5000), // Primeiros 5000 caracteres
                'is_complete' => true,
                'stream_duration' => $streamDuration,
            ];
            
            $exceptionData = null;
            if (isset($data['exception'])) {
                $exception = $data['exception'];
                $exceptionData = [
                    'message' => $exception->getMessage(),
                    'trace' => $exception->getTrace(),
                    'code' => $exception->getCode(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'file_snippet' => $this->getfileSnippet($exception),
                ];
            }
            
            $data['lonomia']->logPerformanceData([
                'tracking_id' => $trackingId,
                'request' => [
                    'method' => $data['request']->method(),
                    'url' => $data['request']->fullUrl(),
                    'headers' => $data['request']->headers->all(),
                    'body' => $this->getRequestBody($data['request']),
                ],
                'response' => [
                    'status' => 200,
                    'headers' => ['Content-Type' => 'text/event-stream'],
                    'body' => $responseBody,
                ],
                        'performance' => $performanceData,
                        'queries' => $data['queries'],
                        'http_requests' => $data['lonomia']->getHttpRequests(), // Inclui requisições HTTP
                        'apm' => $data['lonomia']->getApmData(),
                        'logs' => $data['lonomia']->getLogs(),
                        'exception' => $exceptionData,
                    ]);
        }
        
        // Limpa os dados do stream após processamento
        unset(self::$streamData[$streamId]);
        
        // Limpa requisições HTTP após processamento do stream
        $data['lonomia']->clearHttpRequests();
        $data['lonomia']->clearExternalRequests();
    }

    /**
     * Captura o corpo do request, se for JSON ou form data.
     */
    private function getRequestBody(Request $request)
    {
        if ($request->isJson()) {
            return $request->json()->all();
        }

        if ($request->isMethod('POST') || $request->isMethod('PUT') || $request->isMethod('PATCH')) {
            return $request->all();
        }

        return null;
    }

    /**
     * Captura o corpo do response apenas se for JSON.
     */
    private function getJsonResponseBody($response)
    {
        $contentType = $response->headers->get('content-type');
        if ($contentType && str_contains($contentType, 'application/json')) {
            return json_decode($response->getContent(), true);
        }
        return null;
    }

    function getfileSnippet(Throwable $exception, int $contextLines = 3): ?array
{
    $exceptionData = [
        'message' => $exception->getMessage(),
        'trace' => $exception->getTrace(),
        'code' => $exception->getCode(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'previous' => $exception->getPrevious(),
        'file_snippet' => [],
    ];

    // Verifica se o arquivo do erro existe
    if (file_exists($exceptionData['file'])) {
        $fileLines = file($exceptionData['file']); // Lê todas as linhas do arquivo
        $totalLines = count($fileLines);
        $errorLine = $exceptionData['line'];

        // Define o intervalo de linhas a serem exibidas
        $startLine = max(0, $errorLine - $contextLines - 1);
        $endLine = min($totalLines - 1, $errorLine + $contextLines - 1);

        // Captura as linhas do arquivo ao redor do erro
        return  array_slice($fileLines, $startLine, ($endLine - $startLine + 1), true);
    }

    return null;
}

}
