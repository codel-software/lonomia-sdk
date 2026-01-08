<?php

namespace CodelSoftware\LonomiaSdk\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void addExternalRequest(string $url, string $method = 'GET', ?array $requestHeaders = null, mixed $requestBody = null, ?int $statusCode = null, ?array $responseHeaders = null, mixed $responseBody = null, ?float $executionTime = null, bool $success = true, ?string $errorMessage = null)
 * @method static array getExternalRequests()
 * @method static void clearExternalRequests()
 */
class Lonomia extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'lonomia-sdk';
    }
}
