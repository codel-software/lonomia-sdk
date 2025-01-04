<?php

namespace CodelSoftware\LonomiaSdk\Middleware;

use Closure;
use CodelSoftware\LonomiaSdk\Facades\Lonomia;

class CaptureErrors
{
    public function handle($request, Closure $next)
    {
        try {
            return $next($request);
        } catch (\Throwable $exception) {
            Lonomia::captureError($exception, config('lonomia.project_token'));
            throw $exception;
        }
    }
}