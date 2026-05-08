<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogRequestTiming
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->enabled()) {
            return $next($request);
        }

        $start = microtime(true);

        /** @var Response $response */
        $response = $next($request);

        $durationMs = round((microtime(true) - $start) * 1000, 1);
        $thresholdMs = (float) env('REQUEST_TIMING_LOG_THRESHOLD_MS', 0);

        if ($durationMs < $thresholdMs) {
            return $response;
        }

        logger()->info('request_timing', [
            'method' => $request->method(),
            'path' => '/'.ltrim($request->path(), '/'),
            'route' => $request->route()?->getName(),
            'controller' => $request->route()?->getActionName(),
            'status' => $response->getStatusCode(),
            'duration_ms' => $durationMs,
            'memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 1),
        ]);

        return $response;
    }

    private function enabled(): bool
    {
        return filter_var(env('REQUEST_TIMING_LOG_ENABLED', false), FILTER_VALIDATE_BOOL);
    }
}
