<?php

declare(strict_types=1);

namespace Modules\Community\app\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Community\app\Models\Community;
use Modules\Issue\app\Models\Issue;
use Symfony\Component\HttpFoundation\Response;

class ManagerSuperAdminOrAssignedProviderMiddleware
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

        /*
        |--------------------------------------------------------------------------
        | Super Admin
        |--------------------------------------------------------------------------
        */

        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        /*
        |--------------------------------------------------------------------------
        | Get Community ID
        |--------------------------------------------------------------------------
        */

        $communityId = $request->route('community')
            ?? $request->route('communityId');

        if (! $communityId && $request->has('community_id')) {
            $communityId = $request->input('community_id');
        }

        if (! $communityId) {
            return response()->json([
                'message' => 'Community ID is required.',
            ], 400);
        }

        /*
        |--------------------------------------------------------------------------
        | Manager
        |--------------------------------------------------------------------------
        */

        if ($user->isManager()) {
            $community = Community::find($communityId);

            if (! $community) {
                return response()->json([
                    'message' => 'Community not found.',
                ], 404);
            }

            $isManagerOfCommunity = $community
                ->managers()
                ->where('manager_id', $user->id)
                ->exists();

            if (! $isManagerOfCommunity) {
                return response()->json([
                    'message' => 'Unauthorized. You are not a manager of this community.',
                ], 403);
            }

            return $next($request);
        }

        /*
        |--------------------------------------------------------------------------
        | Provider
        |--------------------------------------------------------------------------
        */

        if ($user->isProvider()) {
            $issueId = $request->route('issue');

            if (! $issueId) {
                return response()->json([
                    'message' => 'Issue ID is required.',
                ], 400);
            }

            $issue = Issue::where('community_id', $communityId)
                ->whereKey($issueId)
                ->first();

            if (! $issue) {
                return response()->json([
                    'message' => 'Issue not found.',
                ], 404);
            }

            if ((int) $issue->assigned_to !== (int) $user->id) {
                return response()->json([
                    'message' => 'Unauthorized. This issue is not assigned to you.',
                ], 403);
            }

            return $next($request);
        }



        return response()->json([
            'message' => 'Unauthorized. This action requires Manager, Provider, or Super Admin role.',
        ], 403);
    }
}