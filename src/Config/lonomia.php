<?php

return [
    'api_key' => env('LOMONIA_API_KEY', ''),
    'debug' => env('LOMONIA_DEBUG', false),

    // Timeout para requisição HTTP ao servidor Lonomia (em segundos)
    'http_timeout' => env('LOMONIA_HTTP_TIMEOUT', 1.5),

    // Limites de tamanho de payload (em bytes)
    'max_payload_ok' => env('LOMONIA_MAX_PAYLOAD_OK', 300 * 1024), // 300KB
    'max_payload_error' => env('LOMONIA_MAX_PAYLOAD_ERROR', 1536 * 1024), // 1.5MB

    // Configurações de redução de dados
    'reduction' => [
        // Redução de body (request/response)
        'body' => [
            'max_string_length' => env('LOMONIA_BODY_MAX_STRING_LENGTH', 500),
            'max_array_items' => env('LOMONIA_BODY_MAX_ARRAY_ITEMS', 50),
            'max_depth' => env('LOMONIA_BODY_MAX_DEPTH', 5),
        ],

        // Redução de logs
        'logs' => [
            'max_count' => env('LOMONIA_LOGS_MAX_COUNT', 100),
            'max_message_length' => env('LOMONIA_LOGS_MAX_MESSAGE_LENGTH', 1000),
        ],

        // Redução de requests
        'requests' => [
            'max_http_requests' => env('LOMONIA_MAX_HTTP_REQUESTS', 50),
            'max_external_requests' => env('LOMONIA_MAX_EXTERNAL_REQUESTS', 50),
        ],

        // Redução de cache
        'cache' => [
            'max_operations' => env('LOMONIA_CACHE_MAX_OPERATIONS', 100),
        ],
    ],
];
