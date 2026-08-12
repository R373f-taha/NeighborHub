<?php

declare(strict_types=1);

namespace Modules\Community\app\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Issue\app\Models\Issue;
use Symfony\Component\HttpFoundation\Response;

class ProviderMiddleware
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $user = $request->user();

        // Authentication
        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated. Please provide a valid token.',
            ], 401);
        }

        // Provider role
        if (! $user->isProvider()) {
            return response()->json([
                'message' => 'Unauthorized. This action requires Provider role.',
            ], 403);
        }

        // Get issue ID from route
        $issueId = $request->route('issue');

        if (! $issueId) {
            return response()->json([
                'message' => 'Issue ID is required.',
            ], 400);
        }

        // Get issue
        $issue = Issue::find($issueId);

        if (! $issue) {
            return response()->json([
                'message' => 'Issue not found.',
            ], 404);
        }

        // Provider can access only issues assigned to them
        if ((int) $issue->assigned_to !== (int) $user->id) {
            return response()->json([
                'message' => 'Unauthorized. This issue is not assigned to you.',
            ], 403);
        }

        return $next($request);
    }
}