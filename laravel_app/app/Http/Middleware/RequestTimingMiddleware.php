<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RequestTimingMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);
        $response = $next($request);
        $durationMs = (int) round((microtime(true) - $start) * 1000);

        $response->headers->set('X-Response-Time-Ms', (string) $durationMs);

        $slowLoggingEnabled = filter_var((string) env('APP_SLOW_REQUEST_LOG', 'false'), FILTER_VALIDATE_BOOL);
        $slowThresholdMs = (int) env('APP_SLOW_REQUEST_MS', 1200);
        if ($slowLoggingEnabled && $durationMs >= $slowThresholdMs) {
            Log::warning('Slow request detected', [
                'method' => $request->method(),
                'path' => $request->path(),
                'query' => $request->query(),
                'status' => method_exists($response, 'getStatusCode') ? $response->getStatusCode() : null,
                'duration_ms' => $durationMs,
                'ip' => $request->ip(),
                'user_id' => session('MaTK'),
            ]);
        }

        return $response;
    }
}
