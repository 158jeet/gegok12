<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class TagorePerformance
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = hrtime(true);
        $response = $next($request);

        if (config('tagore.performance.log_slow_requests')) {
            $elapsedMs = (hrtime(true) - $startedAt) / 1_000_000;
            if ($elapsedMs >= config('tagore.performance.slow_request_ms')) {
                Log::warning('Slow Tagore request', [
                    'method' => $request->method(),
                    'route' => optional($request->route())->getName(),
                    'path' => $request->path(),
                    'elapsed_ms' => round($elapsedMs, 2),
                    'memory_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
                ]);
            }
        }

        return $response;
    }
}
