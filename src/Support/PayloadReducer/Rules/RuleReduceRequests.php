<?php

namespace CodelSoftware\LonomiaSdk\Support\PayloadReducer\Rules;

use CodelSoftware\LonomiaSdk\Support\PayloadReducer\ReductionRule;

/**
 * Regra de redução para http_requests e external_requests.
 *
 * Prioridade 3.
 * Limita quantidade de requests, reduz headers e bodies.
 */
class RuleReduceRequests extends ReductionRule
{
    public function getPriority(): int
    {
        return 3;
    }

    public function apply(array $payload, int $targetLimit): array
    {
        try {
            $maxHttpRequests = config('lonomia.reduction.requests.max_http_requests', 50);
            $maxExternalRequests = config('lonomia.reduction.requests.max_external_requests', 50);

            // Reduz http_requests
            if (isset($payload['http_requests']) && is_array($payload['http_requests'])) {
                $payload['http_requests'] = $this->reduceHttpRequests(
                    $payload['http_requests'],
                    $maxHttpRequests
                );
            }

            // Reduz external_requests
            if (isset($payload['external_requests']) && is_array($payload['external_requests'])) {
                $payload['external_requests'] = $this->reduceExternalRequests(
                    $payload['external_requests'],
                    $maxExternalRequests
                );
            }

            return $payload;
        } catch (\Throwable $e) {
            // Em caso de erro, retorna payload original
            return $payload;
        }
    }

    /**
     * Reduz array de http_requests.
     */
    private function reduceHttpRequests(array $requests, int $maxCount): array
    {
        if (count($requests) <= $maxCount) {
            $requestsToProcess = $requests;
        } else {
            // Mantém os últimos N requests (mais recentes)
            $requestsToProcess = array_slice($requests, -$maxCount);
        }

        $reduced = [];
        foreach ($requestsToProcess as $request) {
            $reducedRequest = $request;

            // Reduz headers - mantém apenas essenciais
            if (isset($request['headers']) && is_array($request['headers'])) {
                $reducedRequest['headers'] = $this->reduceHeaders($request['headers']);
            }

            // Reduz body
            if (isset($request['body']) && $request['body'] !== null) {
                $reducedRequest['body'] = $this->truncator->truncateNestedStructure(
                    $request['body'],
                    3,
                    500,
                    20
                );
            }

            // Reduz response_body
            if (isset($request['response_body']) && $request['response_body'] !== null) {
                if (is_string($request['response_body'])) {
                    $reducedRequest['response_body'] = $this->truncator->truncateString(
                        $request['response_body'],
                        1000
                    );
                } else {
                    $reducedRequest['response_body'] = $this->truncator->truncateNestedStructure(
                        $request['response_body'],
                        3,
                        500,
                        20
                    );
                }
            }

            // Reduz response_headers
            if (isset($request['response_headers']) && is_array($request['response_headers'])) {
                $reducedRequest['response_headers'] = $this->reduceHeaders($request['response_headers']);
            }

            $reduced[] = $reducedRequest;
        }

        return $reduced;
    }

    /**
     * Reduz array de external_requests.
     */
    private function reduceExternalRequests(array $requests, int $maxCount): array
    {
        if (count($requests) <= $maxCount) {
            $requestsToProcess = $requests;
        } else {
            // Mantém os últimos N requests (mais recentes)
            $requestsToProcess = array_slice($requests, -$maxCount);
        }

        $reduced = [];
        foreach ($requestsToProcess as $request) {
            $reducedRequest = $request;

            // Reduz request_headers
            if (isset($request['request_headers']) && is_array($request['request_headers'])) {
                $reducedRequest['request_headers'] = $this->reduceHeaders($request['request_headers']);
            }

            // Reduz request_body
            if (isset($request['request_body']) && $request['request_body'] !== null) {
                $reducedRequest['request_body'] = $this->truncator->truncateNestedStructure(
                    $request['request_body'],
                    3,
                    500,
                    20
                );
            }

            // Reduz response_headers
            if (isset($request['response_headers']) && is_array($request['response_headers'])) {
                $reducedRequest['response_headers'] = $this->reduceHeaders($request['response_headers']);
            }

            // Reduz response_body
            if (isset($request['response_body']) && $request['response_body'] !== null) {
                if (is_string($request['response_body'])) {
                    $reducedRequest['response_body'] = $this->truncator->truncateString(
                        $request['response_body'],
                        1000
                    );
                } else {
                    $reducedRequest['response_body'] = $this->truncator->truncateNestedStructure(
                        $request['response_body'],
                        3,
                        500,
                        20
                    );
                }
            }

            $reduced[] = $reducedRequest;
        }

        return $reduced;
    }

    /**
     * Reduz headers mantendo apenas os essenciais.
     */
    private function reduceHeaders(array $headers): array
    {
        // Headers essenciais a manter
        $essentialHeaders = [
            'content-type',
            'content-length',
            'user-agent',
            'accept',
            'authorization', // Mascarado depois
        ];

        $reduced = [];
        foreach ($headers as $key => $value) {
            $lowerKey = strtolower($key);

            // Remove cookies grandes
            if ($lowerKey === 'cookie' && is_array($value) && count($value) > 0) {
                $cookieValue = is_array($value) ? $value[0] : $value;
                if (strlen($cookieValue) > 200) {
                    $reduced[$key] = ['[cookie truncado - '.strlen($cookieValue).' bytes]'];
                    continue;
                }
            }

            // Mascara Authorization
            if ($lowerKey === 'authorization' && is_array($value) && count($value) > 0) {
                $authValue = is_array($value) ? $value[0] : $value;
                if (strlen($authValue) > 20) {
                    $reduced[$key] = [substr($authValue, 0, 20).'...'];
                    continue;
                }
            }

            // Mantém headers essenciais ou pequenos
            if (in_array($lowerKey, $essentialHeaders) || strlen(json_encode($value)) < 200) {
                $reduced[$key] = $value;
            }
        }

        return $reduced;
    }
}
