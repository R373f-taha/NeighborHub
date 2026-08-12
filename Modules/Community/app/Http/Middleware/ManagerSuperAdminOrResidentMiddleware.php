<?php

declare(strict_types=1);

namespace Modules\Community\app\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Community\app\Models\Community;
use Modules\Issue\app\Models\Issue;
use Symfony\Component\HttpFoundation\Response;

class ManagerSuperAdminOrResidentMiddleware
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | Authentication
        |--------------------------------------------------------------------------
        */

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated. Please provide a valid token.',
            ], 401);
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
        | Find Community
        |--------------------------------------------------------------------------
        */

        $community = Community::find($communityId);

        if (! $community) {
            return response()->json([
                'message' => 'Community not found.',
            ], 404);
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
        | Manager
        |--------------------------------------------------------------------------
        */

        if ($user->isManager()) {
            $hasAccess = $community
                ->managers()
                ->where('manager_id', $user->id)
                ->exists();

            if ($hasAccess) {
                return $next($request);
            }

            return response()->json([
                'message' => 'Unauthorized. You are not a manager of this community.',
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | Resident
        |--------------------------------------------------------------------------
        */

        if ($user->isResident()) {
            $hasAccess = $user
                ->resident()
                ->where('community_id', $community->id)
                ->where('status', 'active')
                ->where('current_marker', true)
                ->exists();

            if ($hasAccess) {
                return $next($request);
            }

            return response()->json([
                'message' => 'Unauthorized. You do not belong to this community.',
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | Assigned Provider
        |--------------------------------------------------------------------------
        |
        | Provider can view ONLY an issue assigned to them.
        |
        |
        */

        if ($user->isProvider()) {
            $issueId = $request->route('issue');

            if (! $issueId) {
                return response()->json([
                    'message' => 'Providers can only access issues assigned to them.',
                ], 403);
            }

            $issue = Issue::query()
                ->where('community_id', $community->id)
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
            'message' => 'Unauthorized. You do not have access to this community.',
        ], 403);
    }
}