<?php

return [
    'api_key' => env('LOMONIA_API_KEY', ''),
    'debug' => env('LOMONIA_DEBUG', false),

    // Timeout para requisição HTTP ao servidor Lonomia (em segundos)
    'http_timeout' => env('LOMONIA_HTTP_TIMEOUT', 1.5),

    // Limites de tamanho de payload (em bytes)
    'max_payload_ok' => env('LOMONIA_MAX_PAYLOAD_OK', 300 * 1024), // 300KB
    'max_payload_error' => env('LOMONIA_MAX_PAYLOAD_ERROR', 1536 * 1024), // 1.5MB

    // Configurações de redução de dados (defaults agressivos para payload bem pequeno)
    'reduction' => [
        // Redução de body (request/response)
        'body' => [
            'max_string_length' => env('LOMONIA_BODY_MAX_STRING_LENGTH', 200),
            'max_array_items' => env('LOMONIA_BODY_MAX_ARRAY_ITEMS', 15),
            'max_depth' => env('LOMONIA_BODY_MAX_DEPTH', 3),
        ],

        // Redução de logs
        'logs' => [
            'max_count' => env('LOMONIA_LOGS_MAX_COUNT', 30),
            'max_message_length' => env('LOMONIA_LOGS_MAX_MESSAGE_LENGTH', 300),
        ],

        // Redução de requests
        'requests' => [
            'max_http_requests' => env('LOMONIA_MAX_HTTP_REQUESTS', 10),
            'max_external_requests' => env('LOMONIA_MAX_EXTERNAL_REQUESTS', 10),
            'max_response_body_array_items' => env('LOMONIA_REQUESTS_MAX_RESPONSE_ARRAY_ITEMS', 10),
            'max_response_body_string_length' => env('LOMONIA_REQUESTS_MAX_RESPONSE_STRING_LENGTH', 200),
        ],

        // Redução de cache
        'cache' => [
            'max_operations' => env('LOMONIA_CACHE_MAX_OPERATIONS', 20),
        ],

        // Redução de queries SQL
        'queries' => [
            'max_count' => env('LOMONIA_QUERIES_MAX_COUNT', 20),
            'max_sql_length' => env('LOMONIA_QUERIES_MAX_SQL_LENGTH', 250),
            'max_bindings_count' => env('LOMONIA_QUERIES_MAX_BINDINGS_COUNT', 5),
            'max_binding_length' => env('LOMONIA_QUERIES_MAX_BINDING_LENGTH', 80),
        ],

        // Redução de jobs (filas)
        'jobs' => [
            'max_count' => env('LOMONIA_JOBS_MAX_COUNT', 5),
        ],
    ],
];
