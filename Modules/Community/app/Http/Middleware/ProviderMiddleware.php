<?php

namespace Modules\Community\app\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ProviderMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user()) {
            return response()->json([
                'message' => 'Unauthenticated. Please provide a valid token.',
            ], 401);
        }
          if (!$request->user()->hasRole('provider')) {
            return response()->json([
                'message' => 'Unauthorized. This action requires Provider role.',
            ], 403);
        }

        if (!$request->user()->isProvider()) {
            return response()->json([
                'message' => 'Unauthorized. This action requires Provider role.',
            ], 403);
        }

        return $next($request);
    }
}
