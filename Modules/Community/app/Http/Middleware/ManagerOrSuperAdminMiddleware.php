<?php

namespace Modules\Community\app\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Community\app\Models\Community;

class ManagerOrSuperAdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // Check authentication
        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated. Please provide a valid token.',
            ], 401);
        }

        // Super Admin has full access
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Check manager role
        if (!$user->isManager()) {
            return response()->json([
                'message' => 'Unauthorized. This action requires Manager or Super Admin role.',
            ], 403);
        }

        // Get community id from route
        $communityId = $request->route('communityId');

        // Fallback if community_id exists in request body
        if (!$communityId && $request->has('community_id')) {
            $communityId = $request->community_id;
        }

        $community = Community::find($communityId);

        if (!$community) {
            return response()->json([
                'message' => 'Community not found.',
            ], 404);
        }

        // Check if manager belongs to this community
        $isManagerOfCommunity = $community->managers()
            ->where('manager_id', $user->id)
            ->exists();

        if (!$isManagerOfCommunity) {
            return response()->json([
                'message' => 'Unauthorized. You are not a manager of this community.',
            ], 403);
        }

        return $next($request);
    }
}