<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class RequestLoggerMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        $response = $next($request);

        $duration = (microtime(true) - $startTime) * 1000;
        $memory = memory_get_usage() - $startMemory;


        Log::channel('daily')->info('API Request', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_id' => Auth::id(),
            'status' => $response->status(),
            'duration' => round($duration, 2) . 'ms',
            'memory' => $this->formatBytes($memory),
        ]);


        if ($duration > 1000) {
            Log::warning('⚠️ Slow Request', [
                'url' => $request->fullUrl(),
                'duration' => round($duration, 2) . 'ms',
                'method' => $request->method(),
                'user_id' => Auth::id(),
            ]);
        }

        if ($response->status() >= 400) {
          Log::error('api_errors',[
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'status' => $response->status(),
                'user_id' => Auth::id(),
                'ip' => $request->ip(),
                'duration' => $duration,
                'created_at' => now(),
            ]);
        }

        return $response;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) return $bytes . 'B';
        if ($bytes < 1048576) return round($bytes / 1024, 2) . 'KB';
        return round($bytes / 1048576, 2) . 'MB';
    }
}
