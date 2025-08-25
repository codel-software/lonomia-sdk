<?php

namespace CodelSoftware\LonomiaSdk\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use CodelSoftware\LonomiaSdk\Services\LonomiaService;
use Illuminate\Support\Facades\Auth;
use Throwable;

class CaptureErrors
{
    protected $lonomia;

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
                $this->lonomia->endTag('request-total');

                $exceptionData = null;
                if($response->exception){
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
            $performanceData = [
                'execution_time' => $endTime - $startTime,
                'memory_start' => $startMemory,
                'memory_end' => $endMemory,
                'peak_memory' => $peakMemory,
            ];


            // Envia os dados para o Lonomia
            $this->lonomia->logPerformanceData([
                'tracking_id' => $trackingId,
                'request' => [
                    'method' => $request->method(),
                    'url' => $request->fullUrl(),
                    'headers' => $request->headers->all(),
                    'body' => $this->getRequestBody($request),
                ],
                'response' => isset($response) ? [
                    'status' => $response->getStatusCode(),
                    'headers' => $response->headers->all(),
                    'body' => $this->getJsonResponseBody($response),
                ] : null,
                'performance' => $performanceData,
                'queries' => $queries,
                'apm' => $this->lonomia->getApmData(), // Inclui todas as tags APM criadas
                'logs' => $this->lonomia->getLogs(),
                'exception' => $exceptionData, // Adiciona os dados da exceção, se houver
            ]);

            // Garante que o cookie de rastreamento está presente na resposta
            if (!Cookie::hasQueued($cookieName)) {
                Cookie::queue(Cookie::make($cookieName, $trackingId, 525600));
            }

            return $response;
        }catch(\Throwable $e){
            dd($e);
            return $response;
        }
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

    function getfileSnippet(Throwable $exception, int $contextLines = 3): array
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
