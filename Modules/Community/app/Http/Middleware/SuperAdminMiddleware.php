<?php

namespace Modules\Community\app\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SuperAdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user()) {
            return response()->json([
                'message' => 'Unauthenticated. Please provide a valid token.',
            ], 401);
        }

        if (!$request->user()->isSuperAdmin()) {
            return response()->json([
                'message' => 'Unauthorized. This action requires Super Admin role.',
            ], 403);
        }

             if (!$request->user()->hasRole('super_admin')) {
            return response()->json([
                'message' => 'Unauthorized. This action requires Super Admin role.',
            ], 403);
        }

        return $next($request);
    }
}
