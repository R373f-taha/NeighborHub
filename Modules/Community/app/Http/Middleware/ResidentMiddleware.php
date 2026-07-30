<?php

namespace Modules\Community\app\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ResidentMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user()) {
            return response()->json([
                'message' => 'Unauthenticated. Please provide a valid token.',
            ], 401);
        }

        if (!$request->user()->isResident()) {
            return response()->json([
                'message' => 'Unauthorized. This action requires Resident role.',
            ], 403);
        }

        return $next($request);
    }
}
