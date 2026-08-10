<?php

namespace Modules\Community\app\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Community\app\Models\Community;

/**
 * Authorize super admins, managers of the route's community, OR providers.
 *
 * This is the issue status/progress endpoint counterpart to
 * {@see ManagerOrSuperAdminMiddleware}: the Issue module grants providers the
 * "update_issue_status" and "add_issue_update" permissions (see
 * RolePermissionSeeder), so providers must be able to advance issues assigned
 * across the platform. Managers remain scoped to the community in the URL;
 * providers are platform-wide service workers and are not community members.
 */
class ManagerSuperAdminOrProviderMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated. Please provide a valid token.',
            ], 401);
        }

        if ($user->isSuperAdmin() || $user->isProvider()) {
            return $next($request);
        }

        if (!$user->isManager()) {
            return response()->json([
                'message' => 'Unauthorized. This action requires Manager, Provider, or Super Admin role.',
            ], 403);
        }

        $communityId = $request->route('community')
            ?? $request->route('communityId');

        if (!$communityId && $request->has('community_id')) {
            $communityId = $request->community_id;
        }

        $community = Community::find($communityId);

        if (!$community) {
            return response()->json([
                'message' => 'Community not found.',
            ], 404);
        }

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
